<?php

namespace Rushil13579\AdvancedLeaderboards;

use pocketmine\{
    Server,
    Player,

};

use pocketmine\event\Listener;
use pocketmine\event\player\{
    PlayerJoinEvent,
    PlayerDeathEvent,
    PlayerChatEvent
};
use pocketmine\event\entity\{
    EntityDamageEvent,
    EntityDamageByEntityEvent
};
use pocketmine\event\block\{
    BlockBreakEvent,
    BlockPlaceEvent
};

use Rushil13579\AdvancedLeaderboards\Main;

class EventListener implements Listener {

    private $main;

    public function __construct(Main $main){
        $this->main = $main;
    }


    // PLAYER EVENTS


    public function onJoin(PlayerJoinEvent $ev){
        $this->main->joinDataAdding($ev->getPlayer());

        $this->main->addJoin($ev->getPlayer());
    }

    public function onChat(PlayerChatEvent $ev){
        $player = $ev->getPlayer();
        $msg = $ev->getMessage();

        if($msg !== 'confirm'){
            return null;
        }

        if(isset($this->main->lbmove[$player->getName()])){
            if($this->main->lbmove[$player->getName()] !== 'pending'){
                $entity = $this->main->lbmove[$player->getName()];
                $entity->teleport($player);
                $msg = $this->main->cfg->get('leaderboard-moved-msg');
                $msg = $this->main->formatMessage($msg);
                $msg = $this->main->generateLeaderboardMsg($entity, $msg);
                $player->sendMessage($msg);
                unset($this->main->lbmove[$player->getName()]);
                $ev->setCancelled();
            }
        }
    }

    public function onDeath(PlayerDeathEvent $ev){
        $player = $ev->getPlayer();
        $cause = $player->getLastDamageCause();
        if($cause instanceof EntityDamageByEntityEvent){
            $damager = $cause->getDamager();
            if($damager instanceof Player){
                $this->main->addDeath($player);
                $this->main->addKill($damager);
                $this->main->reviseKDR($player);
                $this->main->reviseKDR($damager);
                $this->main->resetKS($player);
                $this->main->addKS($damager);
                $this->main->addHKS($damager);
            }
        }
    }


    // ENTITY EVENTS


    public function onDamage(EntityDamageEvent $ev){
        $entity = $ev->getEntity();
        if($this->main->isALEntity($entity) === null){
            return null;
        }

        if($ev instanceof EntityDamageByEntityEvent){
            $damager = $ev->getDamager();
            if($damager instanceof Player){

                if(isset($this->main->lbremove[$damager->getName()])){
                    $entity->flagForDespawn();
                    unset($this->main->lbremove[$damager->getName()]);
                    $msg = $this->main->formatMessage($this->main->cfg->get('leaderboard-removed-msg'));
                    $damager->sendMessage($this->main->generateLeaderboardMsg($entity, $msg));
                }

                if(isset($this->main->lbmove[$damager->getName()])){
                    if($this->main->lbmove[$damager->getName()] == 'pending'){
                        $this->main->lbmove[$damager->getName()] = $entity;
                        $damager->sendMessage($this->main->formatMessage($this->main->cfg->get('leaderboard-move-confirm-msg')));
                    }
                }
            }
        }
        
        $ev->setCancelled();
    }


    // BLOCK EVENTS


    public function onPlace(BlockPlaceEvent $ev){
        if($ev->isCancelled()){
            return null;
        }

        $this->main->addBlockPlace($ev->getPlayer());
    }

    public function onBreak(BlockBreakEvent $ev){
        if($ev->isCancelled()){
            return null;
        }

        $this->main->addBlockBreak($ev->getPlayer());
    }
}