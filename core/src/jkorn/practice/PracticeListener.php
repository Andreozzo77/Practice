<?php

declare(strict_types=1);

namespace jkorn\practice;


use jkorn\practice\games\misc\gametypes\IGame;
use jkorn\practice\items\TapItem;
use jkorn\practice\items\TapItemManager;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\Server;
use jkorn\practice\items\PracticeItem;
use jkorn\practice\player\info\ClientInfo;
use jkorn\practice\player\PracticePlayer;

class PracticeListener implements Listener
{

    /** @var PracticeCore */
    private $core;
    /** @var Server */
    private $server;

    public function __construct(PracticeCore $core)
    {
        $this->core = $core;
        $this->server = $core->getServer();

        $this->server->getPluginManager()->registerEvents($this, $core);
    }

    /**
     * @param PlayerCreationEvent $event
     *
     * Called when the player is instantiated.
     */
    public function onCreation(PlayerCreationEvent $event)
    {
        $event->setPlayerClass(PracticePlayer::class);
    }

    /**
     * @param PlayerJoinEvent $event
     *
     * Called when the player first joins the server.
     */
    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        if($player instanceof PracticePlayer) {
            $player->onJoin($event);
        }
    }

    /**
     * @param PlayerQuitEvent $event
     *
     * Called when the player quits the game.
     */
    public function onQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();

        if ($player instanceof PracticePlayer) {
            $player->onLeave($event);
        }
    }

    /**
     * @param PlayerInteractEvent $event
     *
     * Called when the player interacts with an item.
     */
    public function onInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();

        if ($player !== null && $player instanceof PracticePlayer) {
            $clientInfo = $player->getClientInfo();
            $item = $event->getItem();

            $action = $event->getAction();
            if ($action === PlayerInteractEvent::RIGHT_CLICK_BLOCK && $clientInfo instanceof ClientInfo && $clientInfo->isPE()) {
                $player->onClick(true);
            }

            if (
                ($actionItem = PracticeCore::getDefaultItemManager()->getPracticeItem($item)) !== null
                && $actionItem instanceof PracticeItem
            ) {
                $event->setCancelled($actionItem->execute($player));
            }
            // Called when the player used a tap item.
            elseif (
                ($tapItem = TapItemManager::getTapItem($item)) !== null
                && $tapItem instanceof TapItem
            )
            {
                // Called to determine if the item is used.
                $useItem = $action === PlayerInteractEvent::RIGHT_CLICK_AIR || $action === PlayerInteractEvent::RIGHT_CLICK_BLOCK;
                if($useItem && !$player->getClientInfo()->isPE())
                {
                    $useItem = $action !== PlayerInteractEvent::RIGHT_CLICK_BLOCK;
                }

                if($useItem)
                {
                    $event->setCancelled($tapItem->onItemUse($player, $item, $action));
                    return;
                }

                $event->setCancelled();
            }
        }
    }

    /**
     * @param PlayerDeathEvent $event
     *
     * Called when the player dies.
     */
    public function onDeath(PlayerDeathEvent $event): void
    {
        $player = $event->getPlayer();
        if
        (
            $player instanceof PracticePlayer
            && ($game = $player->getCurrentGame()) !== null
            && $game instanceof IGame
        )
        {
            $game->handleEvent($event);
        }
    }

    /**
     * @param PlayerRespawnEvent $event
     *
     * Called when the player respawns.
     */
    public function onRespawn(PlayerRespawnEvent $event): void
    {
        $player = $event->getPlayer();

        if
        (
            $player instanceof PracticePlayer
            && ($game = $player->getCurrentGame()) !== null
            && $game instanceof IGame
        )
        {
            // The game handles the event.
            $game->handleEvent($event);
        }
        else
        {
            // By default, send the player to the lobby.
            $player->putInLobby(false);
        }
    }

    /**
     * @param EntityDamageEvent $event
     *
     * Called when an entity receives damage.
     */
    public function onEntityDamage(EntityDamageEvent $event): void
    {
        $damagedEntity = $event->getEntity();

        if($damagedEntity instanceof PracticePlayer)
        {
            if
            (
                ($game = $damagedEntity->getCurrentGame()) !== null
                && $game instanceof IGame
            )
            {
                $game->handleEvent($event);
            }
            // Covers everything else.
            elseif
            (
                $damagedEntity->isSpectatingGame()
                || $damagedEntity->isAwaitingForGame()
                || $damagedEntity->isInLobby()
            )
            {
                $event->setCancelled();
            }
        }
    }

    /**
     * @param DataPacketSendEvent $event
     * @priority MONITOR
     * @ignoreCancelled true
     *
     * Called when a data packet is sent from server to client.
     */
    public function onDataPacketSend(DataPacketSendEvent $event): void
    {
        $packet = $event->getPacket(); $player = $event->getPlayer();
        if($packet instanceof ModalFormRequestPacket && $player instanceof PracticePlayer)
        {
            $player->getFormURLImageHandler()->onSend();
        }
    }
}