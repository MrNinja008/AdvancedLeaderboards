<?php

namespace Rushil13579\AdvancedLeaderboards;

use pocketmine\{
    Server,
    Player
};

use pocketmine\plugin\PluginBase;

use pocketmine\entity\{
    Entity,
    Skin
};

use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\CompoundTag;

use pocketmine\utils\Config;
use pocketmine\math\Vector3;

use Rushil13579\AdvancedLeaderboards\Commands\{
    LeaderboardCommand,
    StatsCommand
};

use Rushil13579\AdvancedLeaderboards\Tasks\{
    LeaderboardUpdateTask,
    OnlineTimeUpdateTask,
    MoneyUpdateTask
};

use onebone\economyapi\EconomyAPI;
use jojoe77777\FormAPI\SimpleForm;

class Main extends PluginBase {

    public $cfg;

    public $joins;
    public $kills;
    public $deaths;
    public $kdr;
    public $ks;
    public $hks;
    public $bp;
    public $bb;
    public $ot;
    public $money;

    public $lbremove = [];
    public $lbmove = [];

    public $otsession = [];

    const PREFIX = '§3[§bAdvancedLeaderboards§3]';

    const LEADERBOARDS = [
        'Top Joins',
        'Top Kills',
        'Top Deaths',
        'Top KDR',
        'Top Killstreak',
        'Top Highest Killstreak',
        'Top Blocks Placed',
        'Top Blocks Broken',
        'Top Online Time'
    ];


    // STARTUP FUNCTIONS


    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

        $this->saveDefaultConfig();
        $this->cfg = $this->getConfig();

        $this->versionCheck();

        $this->economyapiCheck();

        $this->registerCommands();

        Entity::registerEntity(ALEntity::class, true);

        $this->generateFiles();

        $this->startTasks();
    }

    public function onDisable(){
        foreach($this->otsession as $player => $time){
            $this->ot->set($player, $this->ot->get($player) + $time);
            $this->ot->save();
        }
    }

    public function versionCheck(){
        if($this->cfg->get('version') !== '1.0.0'){
            $this->getLogger()->warning('§cThe configuration file is outdated. Please delete the configuration file and restart your server to install the latest version');
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
    }

    public function economyapiCheck(){
        if($this->cfg->get('topmoney-leaderboard-support') == 'true'){
            if($this->getServer()->getPluginManager()->getPlugin('EconomyAPI') === null){
                $this->getLogger()->warning('§cEconomyAPI not found! Please install EconomyAPI or disable topmoney-leaderboard-support');
                $this->getServer()->getPluginManager()->disablePlugin($this);
            }
        }
    }

    public function registerCommands(){
        $cmdMap = $this->getServer()->getCommandMap();
        $cmdMap->register('stats', new StatsCommand($this));
        $cmdMap->register('leaderboard', new LeaderboardCommand($this));
    }

    public function generateFiles(){
        @mkdir($this->getDataFolder() . 'playerdata/');
        $this->joins = new Config($this->getDataFolder() . 'playerdata/' . 'joins.yml', Config::YAML);
        $this->kills = new Config($this->getDataFolder() . 'playerdata/' . 'kills.yml', Config::YAML);
        $this->deaths = new Config($this->getDataFolder() . 'playerdata/' . 'deaths.yml', Config::YAML);
        $this->kdr = new Config($this->getDataFolder() . 'playerdata/' . 'kdr.yml', Config::YAML);
        $this->ks = new Config($this->getDataFolder() . 'playerdata/' . 'killstreak.yml', Config::YAML);
        $this->hks = new Config($this->getDataFolder() . 'playerdata/' . 'highestkillstreak.yml', Config::YAML);
        $this->bp = new Config($this->getDataFolder() . 'playerdata/' . 'blocksplaced.yml', Config::YAML);
        $this->bb = new Config($this->getDataFolder() . 'playerdata/' . 'blocksbroken.yml', Config::YAML);
        $this->ot = new Config($this->getDataFolder() . 'playerdata/' . 'onlinetime.yml', Config::YAML);

        if($this->cfg->get('topmoney-leaderboard-support') == 'true'){
            $this->money = new Config($this->getDataFolder() . 'playerdata/' . 'money.yml', Config::YAML);
        }
    }

    public function startTasks(){
        $this->getScheduler()->scheduleRepeatingTask(new LeaderboardUpdateTask($this), 20 * $this->cfg->get('leaderboard-update-time'));
        $this->getScheduler()->scheduleRepeatingTask(new OnlineTimeUpdateTask($this), 20);

        if($this->cfg->get('topmoney-leaderboard-support') == 'true'){
            $this->getScheduler()->scheduleRepeatingTask(new MoneyUpdateTask($this), 20 * $this->cfg->get('topmoney-update-time'));
        }
    }


    // DATA MANAGER


    public function joinDataAdding($player){
        if(!$this->joins->exists($player->getName())){
            $this->joins->set($player->getName(), 0);
            $this->joins->save();
        }

        if(!$this->kills->exists($player->getName())){
            $this->kills->set($player->getName(), 0);
            $this->kills->save();
        }

        if(!$this->deaths->exists($player->getName())){
            $this->deaths->set($player->getName(), 0);
            $this->deaths->save();
        }

        if(!$this->kdr->exists($player->getName())){
            $this->kdr->set($player->getName(), 0);
            $this->kdr->save();
        }

        if(!$this->ks->exists($player->getName())){
            $this->ks->set($player->getName(), 0);
            $this->ks->save();
        }

        if(!$this->hks->exists($player->getName())){
            $this->hks->set($player->getName(), 0);
            $this->hks->save();
        }

        if(!$this->bp->exists($player->getName())){
            $this->bp->set($player->getName(), 0);
            $this->bp->save();
        }

        if(!$this->bb->exists($player->getName())){
            $this->bb->set($player->getName(), 0);
            $this->bb->save();
        }

        if(!$this->ot->exists($player->getName())){
            $this->ot->set($player->getName(), 0);
            $this->ot->save();
        }

        if($this->cfg->get('topmoney-leaderboard-support') == 'true'){
            if(!$this->money->exists($player->getName())){
                $this->money->set($player->getName(), EconomyAPI::getInstance()->myMoney($player));
                $this->money->save();
            }
        }
    }

    public function addJoin($player){
        $this->joins->set($player->getName(), $this->joins->get($player->getName()) + 1);
        $this->joins->save();
    }

    public function addDeath($player){
        $this->deaths->set($player->getName(), $this->deaths->get($player->getName()) + 1);
        $this->deaths->save();
    }

    public function addKill($player){
        $this->kills->set($player->getName(), $this->kills->get($player->getName()) + 1);
        $this->kills->save();
    }

    public function reviseKDR($player){
        $kills = $this->kills->get($player->getName());
        $deaths = $this->deaths->get($player->getName());

        if($deaths == 0){
            $kdr = $kills;
        } else {
            $kdr = round($kills/$deaths, 2);
        }

        $this->kdr->set($player->getName(), $kdr);
        $this->kdr->save();
    }

    public function resetKS($player){
        $this->ks->set($player->getName(), 0);
        $this->ks->save();
    }

    public function addKS($player){
        $this->ks->set($player->getName(), $this->ks->get($player->getName()) + 1);
        $this->ks->save();
    }

    public function addHKS($player){
        $this->hks->set($player->getName(), $this->hks->get($player->getName()) + 1);
        $this->hks->save();
    }

    public function addBlockPlace($player){
        $this->bp->set($player->getName(), $this->bp->get($player->getName()) + 1);
        $this->bp->save();
    }

    public function addBlockBreak($player){
        $this->bb->set($player->getName(), $this->bb->get($player->getName()) + 1);
        $this->bb->save();
    }

    public function updateOnlineTime($player){
        if(!isset($this->otsession[$player->getName()])){
            $this->otsession[$player->getName()] = 0;
            return null;
        }

        $this->otsession[$player->getName()] = $this->otsession[$player->getName()] + 1;
    }

    public function updateMoney($player){
        $this->money->set($player->getName(), EconomyAPI::getInstance()->myMoney($player));
        $this->money->save();
    }


    // MESSAGE MANAGER


    public function formatMessage(string $msg){
        $msg = str_replace(['&', '{line}', '{prefix}'], ['§', "\n", self::PREFIX], $msg);
        return (string) $msg;
    }

    public function generateStatsMsg($player, string $msg){
        $joins = $this->joins->get($player->getName());
        $kills = $this->kills->get($player->getName());
        $deaths = $this->deaths->get($player->getName());
        $kdr = $this->kdr->get($player->getName());
        $ks = $this->ks->get($player->getName());
        $hks = $this->hks->get($player->getName());
        $bp = $this->bp->get($player->getName());
        $bb = $this->bb->get($player->getName());

        $ot = $this->ot->get($player->getName()) + $this->otsession[$player->getName()];
        $hours = floor($ot / 3600);
        $minutes = floor(($ot / 60) % 60);
        $seconds = $ot % 60;

        $fm = str_replace(['{joins}', '{name}', '{kills}', '{deaths}', '{kdr}', '{killstreak}', '{highestkillstreak}', '{blocksbroken}', '{blocksplaced}', '{hours}', '{minutes}', '{seconds}'], [$joins, $player->getName(), $kills, $deaths, $kdr, $ks, $hks, $bp, $bb, $hours, $minutes, $seconds], $msg);
        
        if($this->cfg->get('topmoney-leaderboard-support') == 'true'){
            $money = $this->money->get($player->getName());
            $fm = str_replace('{money}', $money, $fm);
        }

        return (string) $fm;
    }

    public function generateLeaderboardMsg($entity, string $msg){
        $type = $this->typeOfALEntity($entity);
        $msg = str_replace('{leaderboard_type}', $type, $msg);
        return (string) $msg;
    }


    // LEADERBOARD ENTITY MANAGER


    public function spawnLeaderboard($player, $leaderboard){
        $nbt = $this->generateNBT($player, $leaderboard);
        $entity = new ALEntity($player->getLevel(), $nbt);
        $entity->setMaxHealth(1);
        $entity->setImmobile();
        $entity->spawnToAll();
        $this->updateLeaderboard($entity, $leaderboard);
    }

    public function generateNBT($player, $leaderboard){
        $nbt = Entity::createBaseNBT(new Vector3($player->getX(), $player->getY() + 0.5, $player->getZ()));
        $nbt->setString('Type', $leaderboard);
        $skin = new Skin("Standard_Custom", str_repeat("\x00", 8192));
        $nbt->setTag(new CompoundTag("Skin", [
            new StringTag("Data", $skin->getSkinData()),
            new StringTag("Name", $skin->getSkinId())
            ]
        ));
        return $nbt;
    }

    public function isALEntity($entity){
        if($entity instanceof ALEntity){
            return ' ';
        }
        return null;
    }

    public function typeOfALEntity($entity){
        $type = $entity->namedtag->getString('Type');
        return (string) $type;
    }


    // LEADERBOARD MANAGER


    public function updateLeaderboard($entity, $type){
        $joins = $this->joins->getAll();
        arsort($joins);
        $joins = array_slice($joins, 0, $this->cfg->get('leaderboard-length'));

        $kills = $this->kills->getAll();
        arsort($kills);
        $kills = array_slice($kills, 0, $this->cfg->get('leaderboard-length'));

        $deaths = $this->deaths->getAll();
        arsort($deaths);
        $deaths = array_slice($deaths, 0, $this->cfg->get('leaderboard-length'));

        $kdr = $this->kdr->getAll();
        arsort($kdr);
        $kdr = array_slice($kdr, 0, $this->cfg->get('leaderboard-length'));

        $ks = $this->ks->getAll();
        arsort($ks);
        $ks = array_slice($ks, 0, $this->cfg->get('leaderboard-length'));

        $hks = $this->hks->getAll();
        arsort($hks);
        $hks = array_slice($hks, 0, $this->cfg->get('leaderboard-length'));

        $bp = $this->bp->getAll();
        arsort($bp);
        $bp = array_slice($bp, 0, $this->cfg->get('leaderboard-length'));

        $bb = $this->bb->getAll();
        arsort($bb);
        $bb = array_slice($bb, 0, $this->cfg->get('leaderboard-length'));

        $ot = $this->ot->getAll();
        arsort($ot);
        $ot = array_slice($ot, 0, $this->cfg->get('leaderboard-length'));

        if($this->cfg->get('topmoney-leaderboard-support') == 'true'){
            $money = $this->money->getAll();
            arsort($money);
            $money = array_slice($money, 0, $this->cfg->get('leaderboard-length'));
        }

        $converter = [
            'Top Joins' => $joins,
            'Top Kills' => $kills,
            'Top Deaths' => $deaths,
            'Top KDR' => $kdr,
            'Top Killstreak' => $ks,
            'Top Highest Killstreak' => $hks,
            'Top Blocks Placed' => $bp,
            'Top Blocks Broken' => $bb,
            'Top Online Time' => $ot
        ];

        if(array_key_exists($type, $converter)){
            $data = $converter[$type];
        } else {
            if($type == 'Top Money'){
                if(isset($money)){
                    $data = $money;
                }
            }
        }

        $counter = 1;
        $text = $this->formatMessage(str_replace('{leaderboard_name}', $type, $this->cfg->get('leaderboard-title'))) . "\n";
        if(isset($data)){
            foreach($data as $name => $value){

                if($data === $ot){
                    if(isset($this->otsession[$name])){
                        $value += $this->otsession[$name];
                    }
                    $hours = floor($value / 3600);
                    $minutes = floor(($value / 60) % 60);
                    $seconds = $value % 60;
                    $value = $hours . 'H ' . $minutes . 'M ' . $seconds . 'S';
                }

                if($counter == 1){
                    $text .= "\n" . $this->formatMessage(str_replace(['{rank}', '{player_name}', '{value}'], [$counter, $name, $value], $this->cfg->get('leaderboard-lines')[0]));
                }
                if($counter == 2){
                    $text .= "\n" . $this->formatMessage(str_replace(['{rank}', '{player_name}', '{value}'], [$counter, $name, $value], $this->cfg->get('leaderboard-lines')[1]));
                }
                if($counter == 3){
                    $text .= "\n" . $this->formatMessage(str_replace(['{rank}', '{player_name}', '{value}'], [$counter, $name, $value], $this->cfg->get('leaderboard-lines')[2]));
                }
                if($counter > 3){
                    $text .= "\n" . $this->formatMessage(str_replace(['{rank}', '{player_name}', '{value}'], [$counter, $name, $value], $this->cfg->get('leaderboard-lines')[3]));
                }
                $counter++;
            }
        }
        $entity->setNameTag($text);
    }


    // FORMS


    public function sendLeaderboardForm($player){
        $form = new SimpleForm(function (Player $player, $data = null){
            if($data === null){
                return null;
            }

            if($data === 'create'){
                $this->sendCreateForm($player);
            }
            if($data === 'move'){
                $this->lbmove[$player->getName()] = 'pending';
                $player->sendMessage($this->formatMessage($this->cfg->get('leaderboard-move-select-msg')));
            }
            if($data === 'remove'){
                $this->lbremove[$player->getName()] = 'pending';
                $player->sendMessage($this->formatMessage($this->cfg->get('leaderboard-remove-select-msg')));
            }
        });

        $form->setTitle(self::PREFIX);
        $form->addButton('§l§bCreate Leaderboard', '-1', '', 'create');
        $form->addButton('§l§bMove Leaderboard', '-1', '', 'move');
        $form->addButton('§l§bRemove Leaderboard', '-1', '', 'remove');
        $form->sendToPlayer($player);
        return $form;
    }

    public function sendCreateForm($player){
        $form = new SimpleForm(function (Player $player, $data = null){
            if($data === null){
                return null;
            }

            foreach(self::LEADERBOARDS as $leaderboard){
                if($data === $leaderboard){
                    $this->spawnLeaderboard($player, $leaderboard);
                } else {
                    if($data === 'Top Money'){
                        $this->spawnLeaderboard($player, $data);
                        break;
                    }
                }
            }
        });
        $form->setTitle(self::PREFIX);
        foreach(self::LEADERBOARDS as $leaderboard){
            $form->addButton('§l§b' . $leaderboard, '-1', '', $leaderboard);
        }
        if($this->cfg->get('topmoney-leaderboard-support') == 'true'){
            $form->addButton('§l§bTop Money', '-1', '', 'Top Money');
        }
        $form->sendToPlayer($player);
        return $form;
    }
}