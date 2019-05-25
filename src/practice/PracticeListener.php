<?php
/**
 * Created by PhpStorm.
 * User: jkorn2324
 * Date: 2019-04-18
 * Time: 09:17
 */

declare(strict_types=1);

namespace practice;

use pocketmine\block\Liquid;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketFillEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Bucket;
use pocketmine\item\EnderPearl;
use pocketmine\item\FlintSteel;
use pocketmine\item\Food;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\MushroomStew;
use pocketmine\item\Potion;
use pocketmine\item\SplashPotion;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\Player;
use practice\anticheat\AntiCheatUtil;
use practice\arenas\PracticeArena;
use practice\game\FormUtil;
use practice\game\inventory\InventoryUtil;
use practice\game\inventory\menus\inventories\PracBaseInv;
use practice\game\items\PracticeItem;
use practice\player\permissions\PermissionsHandler;
use practice\scoreboard\ScoreboardUtil;
use practice\scoreboard\UpdateScoreboardTask;

class PracticeListener implements Listener
{
    private $core;

    public function __construct(PracticeCore $c)
    {
        $this->core = $c;
    }

    private function getCore(): PracticeCore
    {
        return $this->core;
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $p = $event->getPlayer();

        if (!is_null($p)) {

            $pl = PracticeCore::getPlayerHandler()->addPlayer($p);
            $deviceOS = -1;

            if (!is_null($pl) and PracticeCore::getPlayerHandler()->hasPendingDeviceOs($p)) {
                $deviceOS = PracticeCore::getPlayerHandler()->getPendingDeviceOs($p);
                $pl->setDeviceOS($deviceOS);
                PracticeCore::getPlayerHandler()->removePendingDeviceOs($p);
            }

            if($p->getGamemode() !== 0) $p->setGamemode(0);

            if($p->hasEffects()) $p->removeAllEffects();

            if($p->getHealth() !== $p->getMaxHealth()) $p->setHealth($p->getMaxHealth());

            if($p->isOnFire()) $p->extinguish();

            if(PracticeUtil::isFrozen($p)) PracticeUtil::setFrozen($p, false);

            if(PracticeUtil::isInSpectatorMode($p))

                PracticeUtil::setInSpectatorMode($p, false);

            PracticeCore::getItemHandler()->spawnHubItems($p, true);

            if($deviceOS !== -1 and PracticeCore::getPlayerHandler()->isScoreboardEnabled($p->getName()))
                $pl->initScoreboard($deviceOS);

            ScoreboardUtil::updateSpawnScoreboards('online-players');

            $event->setJoinMessage(PracticeUtil::str_replace(PracticeUtil::getMessage('join-msg'), ['%player%' => $p->getName()]));
        }
    }

    public function onLogin(PlayerLoginEvent $event) : void {

        $p = $event->getPlayer();

        $p->teleport(PracticeUtil::getSpawnPosition());
    }

    public function onLeave(PlayerQuitEvent $event): void {

        $p = $event->getPlayer();

        if (!is_null($p) and PracticeCore::getPlayerHandler()->isPlayer($p)) {

            $pracPlayer = PracticeCore::getPlayerHandler()->getPlayer($p);

            if($pracPlayer->isInParty()) {
                $party = PracticeCore::getPartyManager()->getPartyFromPlayer($pracPlayer->getPlayerName());
                $party->removeFromParty($pracPlayer->getPlayerName());
            }

            if(PracticeCore::getDuelHandler()->isPlayerInQueue($p))
                PracticeCore::getDuelHandler()->removePlayerFromQueue($p);

            if ($pracPlayer->isInCombat()) PracticeUtil::kill($p);

            PracticeCore::getPlayerHandler()->removePlayer($p);
        }

        $msg = PracticeUtil::str_replace(PracticeUtil::getMessage('leave-msg'), ['%player%' => $p->getName()]);

        $event->setQuitMessage($msg);

        PracticeCore::getInstance()->getScheduler()->scheduleDelayedTask(new UpdateScoreboardTask(), 1);
    }

    public function onDeath(PlayerDeathEvent $event): void {

        $p = $event->getPlayer();

        if(PracticeCore::getPlayerHandler()->isPlayerOnline($p)) {

            $player = PracticeCore::getPlayerHandler()->getPlayer($p);
            $lastDamageCause = $p->getLastDamageCause();
            $addToStats = $player->isInArena() and ($player->getCurrentArenaType() === PracticeArena::FFA_ARENA);

            $diedFairly = true;

            if($lastDamageCause != null) {
                if ($lastDamageCause->getCause() === EntityDamageEvent::CAUSE_VOID) {
                    $diedFairly = false;
                    //if($player->isInDuel())
                } elseif ($lastDamageCause->getCause() === EntityDamageEvent::CAUSE_SUICIDE) {
                    $diedFairly = false;
                } elseif ($lastDamageCause->getCause() === EntityDamageEvent::CAUSE_SUFFOCATION) {
                    if ($p->isInsideOfSolid()) {
                        $pos = $p->getPosition();
                        $block = $p->getLevel()->getBlock($pos);
                        if (PracticeUtil::isGravityBlock($block)) {
                            $diedFairly = false;
                        }
                    }
                }
            }
            
            if($addToStats === true) {
                if($diedFairly === true) {
                    if($lastDamageCause instanceof EntityDamageByEntityEvent) {
                        if(PracticeCore::getPlayerHandler()->isPlayerOnline($lastDamageCause->getDamager())) {
                            $attacker = PracticeCore::getPlayerHandler()->getPlayer($lastDamageCause->getDamager());
                            if(!$attacker->equals($player)) {

                                $arena = $attacker->getCurrentArena();

                                if($arena->doesHaveKit()) {
                                    $event->setDrops([]);
                                    $kit = $arena->getFirstKit();
                                    $kit->giveTo($attacker->getPlayer());
                                }

                                PracticeCore::getPlayerHandler()->addKillFor($attacker->getPlayerName());
                                $attacker->updateScoreboard();
                            }
                        }
                    }
                    PracticeCore::getPlayerHandler()->addDeathFor($player->getPlayerName());
                    $player->updateScoreboard();
                }
            } else {

                if($player->isInDuel()) {

                    $duel = PracticeCore::getDuelHandler()->getDuel($p->getPlayer());
                    $winner = ($duel->isPlayer($player->getPlayer()) ? $duel->getOpponent()->getPlayerName() : $duel->getPlayer()->getPlayerName());
                    $loser = $player->getPlayerName();

                    if($diedFairly === true) {
                        $duel->setResults($winner, $loser);
                    } else $duel->setResults();

                    $event->setDrops([]);
                }
            }
        }
    }

    public function onRespawn(PlayerRespawnEvent $event): void {

        $p = $event->getPlayer();

        PracticeUtil::respawnPlayer($p);

        $spawnPos = PracticeUtil::getSpawnPosition();

        $prevSpawnPos = $event->getRespawnPosition();

        if(!PracticeUtil::arePositionsEqual($prevSpawnPos, $spawnPos))
            $event->setRespawnPosition($spawnPos);
    }

    public function onEntityDamaged(EntityDamageEvent $event): void {

        $cancel = false;
        $e = $event->getEntity();

        if($event->getEntity() instanceof Player) {

            if($event->getCause() === EntityDamageEvent::CAUSE_FALL) {
                $cancel = true;
            } else {

                if(PracticeCore::getPlayerHandler()->isPlayerOnline($e)) {
                    $player = PracticeCore::getPlayerHandler()->getPlayer($e);
                    $lvl = $player->getPlayer()->getLevel();

                    if (PracticeUtil::areLevelsEqual($lvl, PracticeUtil::getDefaultLevel()))
                        $cancel = boolval(PracticeUtil::isLobbyProtectionEnabled());

                    if ($cancel === true) $cancel = boolval(!$player->isInDuel());
                } else $cancel = true;
            }
        }

        if($cancel === true) $event->setCancelled();
    }

    public function onEntityDamagedByEntity(EntityDamageByEntityEvent $event): void {

        $entity = $event->getEntity();
        $damager = $event->getDamager();
        if($event->getCause() !== EntityDamageEvent::CAUSE_PROJECTILE
            and $entity instanceof Player and $damager instanceof Player) {
            AntiCheatUtil::checkForReach($entity, $damager);
        }

        $cancel = false;

        if(PracticeCore::getPlayerHandler()->isPlayerOnline($damager) and PracticeCore::getPlayerHandler()->isPlayerOnline($entity)) {

            $attacker = PracticeCore::getPlayerHandler()->getPlayer($damager);
            $attacked = PracticeCore::getPlayerHandler()->getPlayer($entity);

            if(!$attacker->canHitPlayer() or !$attacked->canHitPlayer())
                $cancel = true;

            if($cancel === false) {

                $kb = $event->getKnockBack();
                $attackDelay = $event->getAttackCooldown();

                if($attacker->isInDuel() and $attacked->isInDuel()) {

                    $duel = PracticeCore::getDuelHandler()->getDuel($attacker->getPlayerName());

                    $kit = $duel->getQueue();

                    if(PracticeCore::getKitHandler()->hasKitSetting($kit)) {

                        $pvpData = PracticeCore::getKitHandler()->getKitSetting($kit);

                        $kb = $pvpData->getKB();
                        $attackDelay = $pvpData->getAttackDelay();
                    }
                } elseif ($attacker->isInArena() and $attacked->isInArena()) {

                    $arena = $attacker->getCurrentArena();

                    if($arena->doesHaveKit()) {

                        $kit = $arena->getFirstKit();

                        $name = $kit->getName();

                        if(PracticeCore::getKitHandler()->hasKitSetting($name)) {

                            $pvpData = PracticeCore::getKitHandler()->getKitSetting($name);

                            $kb = $pvpData->getKB();
                            $attackDelay = $pvpData->getAttackDelay();
                        }
                    }
                }

                $event->setAttackCooldown($attackDelay);
                $event->setKnockBack($kb);

                if(AntiCheatUtil::canDamage($attacked->getPlayerName()) and !$event->isCancelled()) {

                    //$attacker->addHit($attacked->getPlayer(), $event->getAttackCooldown());

                    if(!$attacker->isInDuel() and !$attacked->isInDuel()) {
                        $attacker->setInCombat(true);
                        $attacked->setInCombat(true);
                    }

                    if($attacker->isInDuel() and $attacked->isInDuel()) {

                        $duel = PracticeCore::getDuelHandler()->getDuel($attacker->getPlayer());

                        if($duel->isSpleef())
                            $cancel = true;
                        else
                            $duel->addHitFrom($attacked->getPlayer());
                    }
                }
            }
        }

        if($cancel === true) $event->setCancelled();
    }

    public function onPlayerConsume(PlayerItemConsumeEvent $event): void {

        $item = $event->getItem();
        $p = $event->getPlayer();

        $cancel = false;

        if(PracticeUtil::canUseItems($p)) {

            if($item instanceof Food) {

                $isGoldenHead = false;
                if ($item->getId() === Item::GOLDEN_APPLE) $isGoldenHead = ($item->getDamage() === 1 or $item->getName() === PracticeUtil::getName('golden-head'));

                if ($isGoldenHead === true) {

                    $effects = $item->getAdditionalEffects();

                    $size = count($effects);

                    for ($i = 0; $i < $size; $i++) {
                        $effect = $effects[$i];
                        if ($effect instanceof EffectInstance) {
                            $id = $effect->getId();
                            if ($id === Effect::REGENERATION) {
                                $effect = $effect->setDuration(PracticeUtil::secondsToTicks(8))->setAmplifier(1);
                            } elseif ($id === Effect::ABSORPTION) {
                                $effect = $effect->setDuration(PracticeUtil::minutesToTicks(2));
                            }
                        }
                        $effects[$i] = $effect;
                    }

                    foreach($effects as $effect)
                        $p->addEffect($effect);

                    $inv = $p->getInventory();

                    $heldItem = $inv->getHeldItemIndex();

                    $inv->setItem($heldItem, $item->setCount($item->count - 1));

                    $cancel = true;

                } else {
                    if ($item->getId() === Item::MUSHROOM_STEW)
                        $cancel = true;
                }
            } elseif ($item instanceof Potion) {

                $slot = $p->getInventory()->getHeldItemIndex();
                $effects = $item->getAdditionalEffects();

                $p->getInventory()->setItem($slot, Item::get(0));

                foreach($effects as $effect) {
                    if($effect instanceof EffectInstance)
                        $p->addEffect($effect);
                }

                $cancel = true;
            }
        } else $cancel = true;

        if($cancel === true) $event->setCancelled();
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {

        $item = $event->getItem();
        $player = $event->getPlayer();
        $action = $event->getAction();
        $level = $player->getLevel();
        $cancel = false;

        if (PracticeCore::getPlayerHandler()->isPlayer($player)) {
            $p = PracticeCore::getPlayerHandler()->getPlayer($player);

            $exec = PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_BLOCK);
            if ($p->getDevice() !== PracticeUtil::WINDOWS_10 and $exec === true) {
                $p->addClick(false);
            }

            if (PracticeCore::getItemHandler()->isPracticeItem($item)) {

                if (PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_BLOCK, PlayerInteractEvent::RIGHT_CLICK_AIR)) {

                    $practiceItem = PracticeCore::getItemHandler()->getPracticeItem($item);

                    if ($practiceItem instanceof PracticeItem and PracticeCore::getItemHandler()->canUseItem($p, $practiceItem)) {

                        $name = $practiceItem->getLocalizedName();
                        $exec = ($practiceItem->canOnlyUseInLobby() ? PracticeUtil::areLevelsEqual($level, PracticeUtil::getDefaultLevel()) : true);

                        if($exec === true) {

                            if (PracticeUtil::str_contains('hub.', $name)) {
                                if (PracticeUtil::str_contains('unranked-duels', $name)) {
                                    if(PracticeUtil::isItemFormsEnabled()) {
                                        $form = FormUtil::getDuelsForm();
                                        $p->sendForm($form, true);
                                    } else InventoryUtil::sendMatchInv($player);
                                } elseif (PracticeUtil::str_contains('ranked-duels', $name)) {
                                    if(PracticeUtil::isItemFormsEnabled()) {
                                        $form = FormUtil::getDuelsForm(true);
                                        $p->sendForm($form, true, true);
                                    } else InventoryUtil::sendMatchInv($player, true);
                                } elseif (PracticeUtil::str_contains('ffa', $name)) {
                                    if(PracticeUtil::isItemFormsEnabled()) {
                                        $form = FormUtil::getFFAForm();
                                        $p->sendForm($form);
                                    } else InventoryUtil::sendFFAInv($player);
                                } elseif (PracticeUtil::str_contains('duel-inv', $name)) {
                                    $p->spawnResInvItems();
                                } elseif (PracticeUtil::str_contains('settings', $name)) {
                                    $op = PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false);
                                    $form = FormUtil::getSettingsForm($p->getPlayerName(), $op);
                                    $p->sendForm($form);
                                } elseif (PracticeUtil::str_contains('leaderboard', $name))
                                    InventoryUtil::sendLeaderboardInv($player);


                            } elseif ($name === 'exit.inventory') {
                                PracticeCore::getItemHandler()->spawnHubItems($player, true);
                            } elseif ($name === 'exit.queue') {
                                PracticeCore::getDuelHandler()->removePlayerFromQueue($player, true);
                                $p->updateScoreboard();
                                ScoreboardUtil::updateSpawnScoreboards('in-queues');
                            } elseif ($name === 'exit.spectator') {

                                if(PracticeCore::getDuelHandler()->isASpectator($player)) {
                                    $duel = PracticeCore::getDuelHandler()->getDuelFromSpec($player);
                                    $duel->removeSpectator($player, true);
                                } else PracticeUtil::resetPlayer($player);

                                $msg = PracticeUtil::getMessage('spawn-message');
                                $player->sendMessage($msg);
                            }
                        }
                    }
                    $cancel = true;
                }
            } else {

                if ($p->isDuelHistoryItem($item)) {

                    if(PracticeUtil::canUseItems($player, true)) {
                        $name = $item->getName();
                        InventoryUtil::sendResultInv($player, $name);
                    }
                    $cancel = true;

                } else {

                    $checkPlaceBlock = $item->getId() < 255 or PracticeUtil::isSign($item) or $item instanceof ItemBlock or $item instanceof Bucket or $item instanceof FlintSteel;

                    if (PracticeUtil::canUseItems($player)) {

                        if($checkPlaceBlock === true) {
                            if($p->isInArena()) {
                                $cancel = !$p->getCurrentArena()->canBuild();
                            } else {
                                if ($p->isInDuel()) {
                                    $duel = PracticeCore::getDuelHandler()->getDuel($p);
                                    if($duel->isDuelRunning() and $duel->canBuild()) {
                                        $cancel = false;
                                    } else $cancel = true;
                                } else {
                                    $cancel = true;
                                    if (!PracticeUtil::isInSpectatorMode($player) and PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                                        $cancel = !PracticeCore::getPlayerHandler()->canPlaceNBreak($player->getName());
                                }
                            }
                            $event->setCancelled($cancel);
                            return;
                        }

                        if ($item->getId() === Item::FISHING_ROD) {

                            $use = false;

                            $checkActions = PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_AIR, PlayerInteractEvent::RIGHT_CLICK_BLOCK);

                            if (PracticeUtil::isTapToRodEnabled()) {
                                if($checkActions === true) {
                                    if($p->getDevice() === PracticeUtil::WINDOWS_10) {
                                        $use = $action !== PlayerInteractEvent::RIGHT_CLICK_BLOCK;
                                    } else $use = true;
                                }
                            } else {
                                $use = PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_AIR);
                            }

                            if ($use) PracticeUtil::useRod($item, $player);
                            else $cancel = true;

                        } elseif ($item->getId() === Item::ENDER_PEARL and $item instanceof EnderPearl) {

                            $use = false;

                            $checkActions = PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_AIR, PlayerInteractEvent::RIGHT_CLICK_BLOCK);

                            if (PracticeUtil::isTapToPearlEnabled()) {
                                if($checkActions === true) {
                                    if($p->getDevice() === PracticeUtil::WINDOWS_10) {
                                        $use = $action !== PlayerInteractEvent::RIGHT_CLICK_BLOCK;
                                    } else $use = true;
                                }
                            } else {
                                $use = PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_AIR);
                            }

                            if ($use === true) PracticeUtil::throwPearl($item, $player);

                            $cancel = true;
                        } elseif ($item->getId() === Item::SPLASH_POTION and $item instanceof SplashPotion) {

                            $use = false;

                            $checkActions = PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_BLOCK, PlayerInteractEvent::RIGHT_CLICK_AIR);

                            if (PracticeUtil::isTapToPotEnabled()) {
                                if($checkActions === true) {
                                    if($p->getDevice() === PracticeUtil::WINDOWS_10) {
                                        $use = $action !== PlayerInteractEvent::RIGHT_CLICK_BLOCK;
                                    } else $use = true;
                                }
                            } else {
                                $use = PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_AIR);
                            }

                            if ($use) PracticeUtil::throwPotion($item, $player);

                            $cancel = true;

                        } elseif ($item->getId() === Item::MUSHROOM_STEW and $item instanceof MushroomStew) {

                            $inv = $player->getInventory();

                            $inv->setItemInHand(Item::get(Item::AIR));

                            $newHealth = $player->getHealth() + 7.0;

                            if ($newHealth > $player->getMaxHealth()) $newHealth = $player->getMaxHealth();

                            $player->setHealth($newHealth);

                            $cancel = true;
                        }

                    } else {

                        $cancel = true;

                        if($checkPlaceBlock === true) {
                            if($p->isInArena()) {
                                $cancel = !$p->getCurrentArena()->canBuild();
                            } else {
                                if ($p->isInDuel()) {
                                    $duel = PracticeCore::getDuelHandler()->getDuel($p);
                                    if($duel->isDuelRunning() and $duel->canBuild()) {
                                        $cancel = false;
                                    }
                                } else {
                                    if (!PracticeUtil::isInSpectatorMode($player) and PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                                        $cancel = !PracticeCore::getPlayerHandler()->canPlaceNBreak($player->getName());
                                }
                            }
                            $event->setCancelled($cancel);
                            return;
                        }
                    }
                }
            }
        }

        if ($cancel === true) $event->setCancelled();

    }

    public function onBlockPlace(BlockPlaceEvent $event): void {

        $item = $event->getItem();
        $player = $event->getPlayer();
        $cancel = false;

        if (PracticeCore::getPlayerHandler()->isPlayer($player)) {

            $p = PracticeCore::getPlayerHandler()->getPlayer($player);


            if (PracticeCore::getItemHandler()->isPracticeItem($item))
                $cancel = true;

            else {
                if($p->isInArena()) {
                    $cancel = !$p->getCurrentArena()->canBuild();
                } else {
                    if ($p->isInDuel()) {
                        $duel = PracticeCore::getDuelHandler()->getDuel($player->getName());
                        if($duel->isDuelRunning() and $duel->canBuild()) {
                            $duel->addBlock($event->getBlock());
                        } else $cancel = true;
                    } else {
                        $cancel = true;
                        if (!PracticeUtil::isInSpectatorMode($player) and PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                            $cancel = !PracticeCore::getPlayerHandler()->canPlaceNBreak($player->getName());

                    }
                }
            }
        }

        if ($cancel === true) $event->setCancelled();
    }

    public function onBlockBreak(BlockBreakEvent $event): void {

        $item = $event->getItem();
        $player = $event->getPlayer();

        $cancel = false;

        if (PracticeCore::getPlayerHandler()->isPlayer($player)) {

            $p = PracticeCore::getPlayerHandler()->getPlayer($player);

            if (PracticeCore::getItemHandler()->isPracticeItem($item)) {
                $cancel = true;

            } else {

                if($p->isInArena()) {

                    $cancel = !$p->getCurrentArena()->canBuild();

                } else {

                    if ($p->isInDuel()) {
                        $duel = PracticeCore::getDuelHandler()->getDuel($player->getName());
                        if($duel->isDuelRunning() and $duel->canBreak())
                            $cancel = !$duel->removeBlock($event->getBlock());
                        else $cancel = true;
                    } else {

                        $cancel = true;

                        if (!PracticeUtil::isInSpectatorMode($player) and PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                            $cancel = !PracticeCore::getPlayerHandler()->canPlaceNBreak($player->getName());

                    }
                }
            }
        }

        if ($cancel === true) $event->setCancelled();
    }

    //THE SAME AS BLOCKFROMTOEVENT IN NUKKIT
    public function onBlockReplace(BlockFormEvent $event): void {
        $arena = PracticeCore::getArenaHandler()->getArenaClosestTo($event->getBlock());
        $cancel = false;
        if(!is_null($arena) and ($arena->getArenaType() === PracticeArena::DUEL_ARENA)) {
            if(PracticeCore::getDuelHandler()->isArenaInUse($arena->getName())) {
                $duel = PracticeCore::getDuelHandler()->getDuel($arena->getName(), true);
                if($duel->isDuelRunning()) {
                    if($event->getNewState() instanceof Liquid)
                        $duel->addBlock($event->getBlock());
                    else $cancel = true;
                }
                else $cancel = true;
            } else {
                $cancel = true;
            }
        } else {
            $cancel = true;
        }

        if($cancel === true) $event->setCancelled();
    }

    //USE FOR LAVA AND LIQUIDS
    public function onBlockSpread(BlockSpreadEvent $event): void {
        $arena = PracticeCore::getArenaHandler()->getArenaClosestTo($event->getBlock());
        $cancel = false;
        if(!is_null($arena) and ($arena->getArenaType() === PracticeArena::DUEL_ARENA)) {
            if(PracticeCore::getDuelHandler()->isArenaInUse($arena->getName())) {
                $duel = PracticeCore::getDuelHandler()->getDuel($arena->getName(), true);
                if($duel->isDuelRunning()) {
                    if($event->getNewState() instanceof Liquid)
                        $duel->addBlock($event->getBlock());
                    else $cancel = true;
                }
                else $cancel = true;
            } else {
                $cancel = true;
            }
        } else {
            $cancel = true;
        }

        if($cancel === true) $event->setCancelled();
    }

    public function onBucketFill(PlayerBucketFillEvent $event): void {

        $item = $event->getItem();
        $player = $event->getPlayer();

        $cancel = false;

        if (PracticeCore::getPlayerHandler()->isPlayer($player)) {

            $p = PracticeCore::getPlayerHandler()->getPlayer($player);

            if (PracticeCore::getItemHandler()->isPracticeItem($item)) {

                $cancel = true;

            } else {
                if($p->isInArena()) {
                    $cancel = !$p->getCurrentArena()->canBuild();
                } else {

                    if ($p->isInDuel()) {
                        $duel = PracticeCore::getDuelHandler()->getDuel($player->getName());
                        if($duel->isDuelRunning() and $duel->canBuild())
                            $cancel = !$duel->removeBlock($event->getBlockClicked());
                        else $cancel = true;
                    } else {

                        $cancel = true;

                        if (!PracticeUtil::isInSpectatorMode($player) and PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                            $cancel = !PracticeCore::getPlayerHandler()->canPlaceNBreak($player->getName());

                        if (PracticeUtil::areLevelsEqual($player->getLevel(), PracticeUtil::getDefaultLevel()))
                            $cancel = true;
                    }
                }
            }
        }

        if ($cancel === true) $event->setCancelled();
    }

    public function onBucketEmpty(PlayerBucketEmptyEvent $event): void {

        $item = $event->getBucket();
        $player = $event->getPlayer();
        $cancel = false;

        if (PracticeCore::getPlayerHandler()->isPlayer($player)) {

            $p = PracticeCore::getPlayerHandler()->getPlayer($player);

            if (PracticeCore::getItemHandler()->isPracticeItem($item))
                $cancel = true;

            else {
                if($p->isInArena()) {
                    $cancel = !$p->getCurrentArena()->canBuild();
                } else {
                    if ($p->isInDuel()) {
                        $duel = PracticeCore::getDuelHandler()->getDuel($player->getName());
                        if($duel->isDuelRunning() and $duel->canBuild()) {
                            $duel->addBlock($event->getBlockClicked());
                        } else $cancel = true;
                    } else {

                        $cancel = true;

                        if (!PracticeUtil::isInSpectatorMode($player) and PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                            $cancel = !PracticeCore::getPlayerHandler()->canPlaceNBreak($player->getName());

                        if(PracticeUtil::areLevelsEqual($player->getLevel(), PracticeUtil::getDefaultLevel()))
                            $cancel = true;
                    }
                }
            }
        }

        if ($cancel === true) $event->setCancelled();
    }

    public function onFireSpread(BlockBurnEvent $event) {
        $event->setCancelled();
    }

    //TODO TEST
    public function onCommand(CommandEvent $event) : void {

        /*$sender = $event->getSender();
        $commandName = $event->getCommand();

        $map = Server::getInstance()->getCommandMap();

        $cmd = $map->getCommand($commandName);

        $isCommand = !is_null($cmd) and $cmd instanceof Command;

        if($isCommand === true) {

            $permission = $cmd->getPermission();

            if($sender instanceof Player and PracticeUtil::testPermission($sender, $permission, false)) {

                $p = $sender->getPlayer();

                if(!$p->hasPermission($permission))
                    PracticeUtil::addPermissionTo($p, $permission);
            }
        }*/
    }

    //TODO TEST
    public function onCommandPreprocess(PlayerCommandPreprocessEvent $event): void {

        $p = $event->getPlayer();

        $cancel = false;

        if (PracticeCore::getPlayerHandler()->isPlayer($p)) {

            $player = PracticeCore::getPlayerHandler()->getPlayer($p);

            $message = $event->getMessage();

            $firstChar = $message[0];

            $testInAntiSpam = false;

            if($firstChar === '/') {

                $usableCommandsInCombat = ['ping', 'tell', 'say', 'me'];

                $tests = ['/ping', '/tell', '/say', '/me'];

                $sendMsg = PracticeUtil::str_contains_from_arr($message, $tests);

                if(!$player->canUseCommands(!$sendMsg)) {

                    $use = false;

                    foreach($usableCommandsInCombat as $value) {

                        $value = strval($value);

                        $test = '/' . $value;

                        if(PracticeUtil::str_contains($test, $message)) {
                            $use = true;
                            if($value === 'say' or $value === 'me') $testInAntiSpam = true;
                            break;
                        }
                    }

                    if($use === false) $cancel = true;
                }
            } else $testInAntiSpam = true;

            if($testInAntiSpam === true) {

                if(PracticeUtil::canPlayerChat($p)) {
                    if($player->isInAntiSpam()) {
                        $player->sendMessage(PracticeUtil::getMessage('antispam-msg'));
                        $cancel = true;
                    }
                } else $cancel = true;
            }
        }

        if ($cancel === true) $event->setCancelled();
    }

    public function onChat(PlayerChatEvent $event): void {

        $p = $event->getPlayer();
        $cancel = false;
        if (PracticeUtil::isRanksEnabled()) {
            $message = $event->getMessage();
            $event->setFormat(PracticeUtil::getChatFormat($p, $message));
        }

        if (!PracticeUtil::canPlayerChat($p)) $cancel = true;
        else {
            if (PracticeCore::getPlayerHandler()->isPlayer($p)) {
                $player = PracticeCore::getPlayerHandler()->getPlayer($p);
                if (!$player->isInAntiSpam())
                    $player->setInAntiSpam(true);
                else {
                    $player->sendMessage(PracticeUtil::getMessage('antispam-msg'));
                    $cancel = true;
                }
            }
        }

        if ($cancel === true) $event->setCancelled();
    }


    public function onPacketSend(DataPacketSendEvent $event): void {

        $pkt = $event->getPacket();

        if ($pkt instanceof TextPacket) {

            if (!PracticeUtil::isChatFilterEnabled()) {
                if ($pkt->type !== TextPacket::TYPE_TRANSLATION) {
                    $pkt->message = PracticeUtil::getUnFilteredChat($pkt->message);
                }

                $count = 0;
                foreach ($pkt->parameters as $param) {
                    $pkt->parameters[$count] = PracticeUtil::getUnFilteredChat(strval($param));
                    $count++;
                }
            }
        } elseif ($pkt instanceof ContainerClosePacket) {
            if($pkt->windowId === -1) $event->setCancelled();
        }
    }

    public function onPacketReceive(DataPacketReceiveEvent $event): void {

        $pkt = $event->getPacket();
        $player = $event->getPlayer();

        if ($pkt instanceof LoginPacket) {
            $clientData = $pkt->clientData;
            if (array_key_exists('DeviceOS', $clientData)) {
                $device = $clientData['DeviceOS'];
                if (PracticeCore::getPlayerHandler()->isPlayer($player)) {
                    $p = PracticeCore::getPlayerHandler()->getPlayer($device);
                    $p->setDeviceOS(intval($device));
                } else {
                    PracticeCore::getPlayerHandler()->putPendingDevice($pkt->username, intval($device));
                }
            }
        }

        if ($pkt instanceof PlayerActionPacket) {
            if ($pkt->action === PlayerActionPacket::ACTION_START_BREAK and PracticeCore::getPlayerHandler()->isPlayer($player)) {
                $p = PracticeCore::getPlayerHandler()->getPlayer($player);
                if ($p->getDevice() === PracticeUtil::WINDOWS_10)
                    $p->addClick(true);
            }
        } elseif ($pkt instanceof LevelSoundEventPacket) {

            $sound = $pkt->sound;
            $sounds = [41, 42, 43];

            $cancel = PracticeUtil::arr_contains_value($sound, $sounds);

            if ($cancel === true) {

                if (PracticeCore::getPlayerHandler()->isPlayer($player)) {

                    $p = PracticeCore::getPlayerHandler()->getPlayer($player);

                    $p->addClick(false);

                    $inv = $p->getPlayer()->getInventory();

                    $item = $inv->getItemInHand();

                    if(PracticeUtil::canUseItems($p->getPlayer())) {

                        if($item->getId() === Item::FISHING_ROD) {
                            if(PracticeUtil::isTapToRodEnabled()) PracticeUtil::useRod($item, $p->getPlayer(), $p->getDevice() !== PracticeUtil::WINDOWS_10);
                        } elseif ($item->getId() === Item::ENDER_PEARL and $item instanceof EnderPearl) {
                            if(PracticeUtil::isTapToPearlEnabled() and $p->getDevice() !== PracticeUtil::WINDOWS_10) PracticeUtil::throwPearl($item, $p->getPlayer(), true);
                        } elseif ($item->getId() === Item::SPLASH_POTION and $item instanceof SplashPotion) {
                            if(PracticeUtil::isTapToPotEnabled() and $p->getDevice() !== PracticeUtil::WINDOWS_10) PracticeUtil::throwPotion($item, $p->getPlayer(), true);
                        } elseif ($item->getId() === Item::MUSHROOM_STEW and $item instanceof MushroomStew) {
                            if($p->getDevice() !== PracticeUtil::WINDOWS_10) {
                                $inv = $player->getInventory();
                                $inv->setItemInHand(Item::get(Item::AIR));
                                $newHealth = $player->getHealth() + 7.0;
                                if($newHealth > $player->getMaxHealth()) $newHealth = $player->getMaxHealth();
                                $player->setHealth($newHealth);
                            }
                        }
                    }
                }

                $event->setCancelled();
            }
        }

        /* elseif ($pkt instanceof LevelEventPacket) {
            $id = $pkt->evid;
            if($id === LevelEventPacket::EVENT_START_RAIN or $id === LevelEventPacket::EVENT_START_THUNDER) {
                $event->setCancelled();
            }
        }*/
    }

    public function onInventoryClosed(InventoryCloseEvent $event) : void {

        $p = $event->getPlayer();

        if(PracticeCore::getPlayerHandler()->isPlayerOnline($p)) {
            $inv = $event->getInventory();
            if($inv instanceof PracBaseInv) {
                $menu = $inv->getMenu();
                $menu->onInventoryClosed($p);
            }
        }
    }

    public function onItemMoved(InventoryTransactionEvent $event): void {

        $transaction = $event->getTransaction();
        $p = $transaction->getSource();
        $lvl = $p->getLevel();
        $cancel = false;

        if(PracticeCore::getPlayerHandler()->isPlayerOnline($p)) {

            $player = PracticeCore::getPlayerHandler()->getPlayer($p);

            $testInv = false;

            if (PracticeUtil::areLevelsEqual($lvl, PracticeUtil::getDefaultLevel())) {

                if(PracticeUtil::isLobbyProtectionEnabled()) {

                    $cancel = !$player->isInDuel() and !$player->isInArena();

                    $testInv = true;

                    if($cancel === true and PracticeUtil::testPermission($p, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                        $cancel = !PracticeCore::getPlayerHandler()->canPlaceNBreak($p->getName());
                }

            } else $testInv = true;

            $testInv = ($cancel === false) ? true : $testInv;

            if($testInv === true) {

                $actions = $transaction->getActions();

                foreach($actions as $action){

                    if($action instanceof SlotChangeAction){

                        $inventory = $action->getInventory();

                        if($inventory instanceof PracBaseInv){

                            $menu = $inventory->getMenu();

                            $menu->onItemMoved($player, $action);

                            if(!$menu->canEdit()) $cancel = true;
                        }
                    }
                }
            }
        } else $cancel = true;

        if ($cancel === true) $event->setCancelled();
    }

    public function onItemDropped(PlayerDropItemEvent $event): void {

        $p = $event->getPlayer();
        $cancel = false;

        if (PracticeCore::getPlayerHandler()->isPlayer($p)) {

            $player = PracticeCore::getPlayerHandler()->getPlayer($p);
            $level = $p->getLevel();

            if (PracticeUtil::isLobbyProtectionEnabled())
                $cancel = PracticeUtil::areLevelsEqual($level, PracticeUtil::getDefaultLevel()) or $player->isInDuel();

        }

        if ($cancel === true) $event->setCancelled();
    }

    public function onPluginDisabled(PluginDisableEvent $event) : void {

        $plugin = $event->getPlugin();

        if($plugin->getName() === PracticeUtil::PLUGIN_NAME) {

            $onlinePlayers = $this->getCore()->getServer()->getOnlinePlayers();

            foreach($onlinePlayers as $player) {

                if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)) {

                    $p = PracticeCore::getPlayerHandler()->getPlayer($player);

                    if(!$p->isInDuel()) {

                        PracticeUtil::resetPlayer($player);

                    } else {

                        $duel = PracticeCore::getDuelHandler()->getDuel($player);

                        if(!$duel->didDuelEnd())
                            $duel->endDuelPrematurely(true);
                    }
                }
            }
        }
    }
}