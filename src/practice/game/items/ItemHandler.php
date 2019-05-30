<?php
/**
 * Created by PhpStorm.
 * User: jkorn2324
 * Date: 2019-04-19
 * Time: 11:14
 */

declare(strict_types=1);

namespace practice\game\items;

use pocketmine\block\Block;
use pocketmine\block\Skull;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\Player;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\scheduler\Task;
use pocketmine\utils\Color;
use pocketmine\utils\TextFormat;
use practice\arenas\FFAArena;
use practice\kits\Kit;
use practice\player\gameplay\reports\ReportInfo;
use practice\player\PracticePlayer;
use practice\PracticeCore;
use practice\PracticeUtil;

class ItemHandler
{

    /* @var PracticeItem[] */
    private $itemList;

    /* @var int */
    private $hubItemsCount;

    /* @var int */
    private $duelItemsCount;

    /* @var int */
    private $ffaItemsCount;

    /* @var int */
    private $leaderboardItemsCount;

    /* @var ItemTextures */
    private $textures;

    private $potions;

    private $buckets;

    public function __construct()
    {
        $this->itemList = [];
        $this->textures = new ItemTextures();

        $this->potions = [
            'Water Bottle', 'Water Bottle', 'Water Bottle', 'Water Bottle', 'Water Bottle',
            'Potion of Night Vision', 'Potion of Night Vision', 'Potion of Invisibility',
            'Potion of Invisibility', 'Potion of Leaping', 'Potion of Leaping', 'Potion of Leaping',
            'Potion of Fire Resistance', 'Potion of Fire Resistance', 'Potion of Swiftness', 'Potion of Swiftness',
            'Potion of Swiftness', 'Potion of Slowness', 'Potion of Slowness', 'Potion of Water Breathing',
            'Potion of Water Breathing', 'Potion of Healing', 'Potion of Healing', 'Potion of Harming',
            'Potion of Harming', 'Potion of Poison', 'Potion of Poison', 'Potion of Regeneration', 'Potion of Regeneration',
            'Potion of Regeneration', 'Potion of Strength', 'Potion of Strength', 'Potion of Strength', 'Potion of Weakness',
            'Potion of Weakness', 'Potion of Decay'
        ];

        $this->buckets = [
            8 => 'Water Bucket',
            9 => 'Water Bucket',
            10 => 'Lava Bucket',
            11 => 'Lava Bucket'
        ];

        $this->init();
    }

    private function init(): void
    {
        $this->initHubItems();
        $this->initDuelItems();
        $this->initFFAItems();
        $this->initLeaderboardItems();
        $this->initMiscItems();
    }

    private function initHubItems(): void
    {

        $unranked = new PracticeItem('hub.unranked-duels', 0, Item::get(Item::IRON_SWORD)->setCustomName(PracticeUtil::getName('unranked-duels')), 'Iron Sword');
        $ranked = new PracticeItem('hub.ranked-duels', 1, Item::get(Item::DIAMOND_SWORD)->setCustomName(PracticeUtil::getName('ranked-duels')), 'Diamond Sword');
        $ffa = new PracticeItem('hub.ffa', 2, Item::get(Item::IRON_AXE)->setCustomName(PracticeUtil::getName('play-ffa')), 'Iron Axe');
        $leaderboard = new PracticeItem('hub.leaderboard', 4, Item::get(Item::SKULL, 3, 1)->setCustomName(TextFormat::BLUE . '» ' . TextFormat::GREEN . 'Leaderboards ' . TextFormat::BLUE . '«'), 'Steve Head');
        $settings = new PracticeItem('hub.settings', 7, Item::get(Item::CLOCK)->setCustomName(TextFormat::BLUE . '» ' . TextFormat::GOLD . 'Your Settings ' . TextFormat::BLUE . '«'), 'Clock');
        $inv = new PracticeItem('hub.duel-inv', 8, Item::get(Item::CHEST)->setCustomName(PracticeUtil::getName('duel-inventory')), 'Chest');

        $this->itemList = [$unranked, $ranked, $ffa, $leaderboard, $settings, $inv];

        $this->hubItemsCount = 6;
    }

    private function initDuelItems(): void
    {

        $duelKits = PracticeCore::getKitHandler()->getDuelKits();
        $items = [];
        foreach ($duelKits as $kit) {
            $name = $kit->getName();
            if ($kit->hasRepItem()) $items['duels.' . $name] = $kit->getRepItem();
        }

        $count = 0;

        $keys = array_keys($items);

        foreach ($keys as $localName) {

            $i = $items[$localName];

            if ($i instanceof Item)
                $this->itemList[] = new PracticeItem(strval($localName), $count, $i, $this->getTextureOf($i));

            $count++;
        }

        $this->duelItemsCount = $count;
    }

    private function initFFAItems(): void
    {

        $arenas = PracticeCore::getKitHandler()->getFFAArenasWKits();

        $result = [];

        foreach ($arenas as $arena) {

            if ($arena instanceof FFAArena) {

                $kit = $arena->getFirstKit();

                if ($kit->hasRepItem()) {

                    $arenaName = $arena->getName();

                    $name = PracticeUtil::getName('ffa-name');

                    if (PracticeUtil::str_contains(' FFA', $arenaName) and PracticeUtil::str_contains(' FFA', $name))
                        $name = PracticeUtil::str_replace($name, [' FFA' => '']);

                    $name = PracticeUtil::str_replace($name, ['%kit-name%' => $arenaName]);

                    $item = clone $kit->getRepItem();

                    $result['ffa.' . $arena->getLocalizedName()] = $item->setCustomName($name);
                }
            }
        }

        $count = 0;

        $keys = array_keys($result);

        foreach ($keys as $key) {

            $item = $result[$key];

            if ($item instanceof Item)
                $this->itemList[] = new PracticeItem(strval($key), $count, $item, $this->getTextureOf($item));

            $count++;
        }

        $this->ffaItemsCount = $count;
    }

    private function initMiscItems(): void
    {

        $exit_queue = new PracticeItem('exit.queue', 8, Item::get(Item::REDSTONE)->setCustomName(PracticeUtil::getName('leave-queue')), $this->getTextureOf(Item::get(Item::REDSTONE_DUST)));
        $exit_spec = new PracticeItem('exit.spectator', 8, Item::get(Item::DYE, 1)->setCustomName(PracticeUtil::getName('spec-hub')), $this->getTextureOf(Item::get(Item::DYE)), false);
        $exit_inv = new PracticeItem('exit.inventory', 8, Item::get(Item::DYE, 1)->setCustomName(TextFormat::RED . 'Exit'), $this->getTextureOf(Item::get(Item::DYE)));

        array_push($this->itemList, $exit_queue, $exit_spec, $exit_inv);
    }

    private function initLeaderboardItems(): void
    {

        $duelKits = PracticeCore::getKitHandler()->getDuelKits();

        $items = [];

        foreach ($duelKits as $kit) {
            $name = $kit->getName();
            if ($kit->hasRepItem()) $items['leaderboard.' . $name] = $kit->getRepItem();
        }

        $count = 0;

        $keys = array_keys($items);

        foreach ($keys as $localName) {

            $i = $items[$localName];

            if ($i instanceof Item)
                $this->itemList[] = new PracticeItem(strval($localName), $count, $i, $this->getTextureOf($i));

            $count++;
        }

        $globalItem = Item::get(Item::COMPASS)->setCustomName(TextFormat::RED . 'Global');

        $var = 'leaderboard.global';

        $global = new PracticeItem($var, $count, $globalItem, $this->getTextureOf($globalItem));

        $this->itemList[] = $global;

        $this->leaderboardItemsCount = $count + 2;
    }

    private function getTextureOf(Item $item) : string {

        $i = clone $item;

        $name = $i->getVanillaName();

        if($i->getId() === Item::POTION) {
            $meta = $i->getDamage();
            $name = $this->potions[$meta];
        } elseif ($i->getId() === Item::SPLASH_POTION) {
            $meta = $i->getDamage();
            $name = 'Splash ' . $this->potions[$meta];
        } elseif ($i->getId() === Item::BUCKET) {
            $meta = $i->getDamage();
            if(isset($this->buckets[$meta]))
                $name = $this->buckets[$meta];
        }

        return $this->textures->getTexture($name);
    }

    public function reload(): void
    {
        $this->itemList = [];
        $this->init();
    }

    public function spawnHubItems($player, bool $clear = false): void
    {

        if (PracticeCore::getPlayerHandler()->isPlayerOnline($player)) {

            $practicePlayer = PracticeCore::getPlayerHandler()->getPlayer($player);

            $inventory = $practicePlayer->getPlayer()->getInventory();

            if ($clear === true) {
                $inventory->clearAll();
                $practicePlayer->getPlayer()->getArmorInventory()->clearAll();
            }

            for ($i = 0; $i < $this->hubItemsCount; $i++) {

                if (isset($this->itemList[$i])) {

                    $practiceItem = $this->itemList[$i];

                    $localName = $practiceItem->getLocalizedName();

                    if (PracticeUtil::str_contains('hub.', $localName)) {

                        $item = $practiceItem->getItem();
                        $slot = $practiceItem->getSlot();

                        $exec = true;

                        if (!$practicePlayer->hasInfoOfLastDuel()) {
                            if ($practiceItem->getLocalizedName() === 'hub.duel-inv') {
                                $exec = false;
                            }
                        }

                        if ($exec === true) $inventory->setItem($slot, $item);
                    }
                }
            }
        }
    }

    public function spawnQueueItems($player): void
    {

        if (PracticeCore::getPlayerHandler()->isPlayerOnline($player)) {

            $p = PracticeCore::getPlayerHandler()->getPlayer($player);

            $inv = $p->getPlayer()->getInventory();

            $p->getPlayer()->getArmorInventory()->clearAll();

            $inv->clearAll();

            $item = $this->getLeaveQueueItem();

            if ($this->isAPracticeItem($item)) {

                $i = $item->getItem();

                $slot = $item->getSlot();

                $inv->setItem($slot, $i);
            }
        }
    }

    public function spawnSpecItems($player): void
    {

        if (PracticeCore::getPlayerHandler()->isPlayerOnline($player)) {

            $p = PracticeCore::getPlayerHandler()->getPlayer($player);

            $inv = $p->getPlayer()->getInventory();

            $inv->clearAll();

            $p->getPlayer()->getArmorInventory()->clearAll();

            $item = $this->getExitSpectatorItem();

            if ($this->isAPracticeItem($item)) {

                $i = $item->getItem();

                $slot = $item->getSlot();

                $inv->setItem($slot, $i);
            }
        }
    }

    public function canUseItem(PracticePlayer $player, PracticeItem $item): bool
    {
        $result = true;
        if (!is_null($player) and $player->isOnline()) {
            $p = $player->getPlayer();
            $level = $p->getLevel();
            if ($item->canOnlyUseInLobby()) {
                if (!PracticeUtil::areLevelsEqual($level, PracticeUtil::getDefaultLevel())) {
                    $result = false;
                }
            }
        } else {
            $result = false;
        }
        return $result;
    }

    public function isPracticeItem(Item $item): bool
    {
        return $this->indexOf($item) !== -1;
    }

    private function isAPracticeItem($item): bool
    {
        return !is_null($item) and $item instanceof PracticeItem;
    }

    /**
     * @param Item $item
     * @return PracticeItem|null
     */
    public function getPracticeItem(Item $item)
    {
        $result = null;
        if ($this->isPracticeItem($item)) {
            $practiceItem = $this->itemList[$this->indexOf($item)];
            if ($practiceItem instanceof PracticeItem) {
                $result = $practiceItem;
            }
        }
        return $result;
    }

    public function getFromLocalName(string $name)
    {
        foreach ($this->itemList as $item) {
            if ($item instanceof PracticeItem) {
                $localName = $item->getLocalizedName();
                if ($localName === $name) {
                    return $item;
                }
            }
        }
        return null;
    }

    public function getLeaveQueueItem()
    {
        return $this->getFromLocalName('exit.queue');
    }

    public function getExitSpectatorItem()
    {
        return $this->getFromLocalName('exit.spectator');
    }

    public function getExitInventoryItem()
    {
        return $this->getFromLocalName('exit.inventory');
    }

    private function indexOf(Item $item): int
    {
        $result = -1;
        $count = 0;
        foreach ($this->itemList as $i) {
            $practiceItem = $i->getItem();
            if ($this->itemsEqual($practiceItem, $item)) {
                $result = $count;
                break;
            }
            $count++;
        }
        return $result;
    }

    private function itemsEqual(Item $item, Item $item1) : bool {
        return $item->equals($item1, true, false) and $item->getName() === $item1->getName();
    }

    /**
     * @return PracticeItem[]
     */
    public function getDuelItems(): array
    {

        $result = [];

        $start = $this->hubItemsCount;

        $size = $start + $this->duelItemsCount;

        for ($i = $start; $i < $size; $i++) {

            if (isset($this->itemList[$i])) {

                $item = $this->itemList[$i];

                $localName = $item->getLocalizedName();

                if (PracticeUtil::str_contains('duels.', $localName))

                    $result[] = $item;

            }
        }

        return $result;
    }

    public function getLeaderboardItems(): array
    {

        $result = [];

        $size = $this->leaderboardItemsCount;

        $start = $this->hubItemsCount + $this->duelItemsCount;

        $len = $start + $size;

        $leaderboards = PracticeCore::getPlayerHandler()->getCurrentLeaderboards();

        for ($i = $start; $i <= $len; $i++) {

            if (isset($this->itemList[$i])) {

                $practiceItem = $this->itemList[$i];

                $localName = $practiceItem->getLocalizedName();

                if (PracticeUtil::str_contains('leaderboard.', $localName)) {

                    $name = $practiceItem->getName();

                    $uncoloredName = PracticeUtil::getUncoloredString($name);

                    if (PracticeUtil::equals_string($uncoloredName, 'Global', 'global', 'GLOBAL', 'global '))
                        $uncoloredName = 'global';

                    $leaderboard = $leaderboards[$uncoloredName];

                    $item = clone $practiceItem->getItem();

                    $item = $item->setLore($leaderboard);

                    $practiceItem = $practiceItem->setItem($item);

                    $result[] = $practiceItem;
                }
            }
        }

        return $result;
    }


    public function getFFAItems(): array {

        $result = [];

        $start = $this->hubItemsCount + $this->duelItemsCount;

        $size = $start + $this->hubItemsCount;

        for ($i = $start; $i < $size; $i++) {

            if (isset($this->itemList[$i])) {

                $item = $this->itemList[$i];

                $localName = $item->getLocalizedName();

                if (PracticeUtil::str_contains('ffa.', $localName))
                    $result[] = $item;

            }
        }
        /*foreach($this->itemList as $i) {
            if($i instanceof PracticeItem) {
                $localName = $i->getLocalizedName();
                if(PracticeUtil::str_contains('ffa.', $localName)) {
                    $result[] = $i;
                }
            }

        }*/
        return $result;
    }

    public function spawnPartyItems(Player $player, bool $clearInv = false) : void {
        //TODO CREATE PARTY ITEMS
    }
}