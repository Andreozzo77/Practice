<?php

declare(strict_types=1);

namespace practice\data\providers;

use pocketmine\Player;
use practice\data\IDataProvider;

/**
 * Class DefaultDataProvider.
 *
 * This class doesn't do anything except define the default data provider.
 *
 * @package practice\data\providers
 */
class DefaultDataProvider implements IDataProvider
{

    /**
     * @param Player $player
     *
     * Loads the player's data.
     */
    public function loadPlayer(Player $player): void {}

    /**
     * @param Player $player
     * @param bool $async - Determines whether to save async or not.
     *
     * Saves the player's data.
     */
    public function savePlayer(Player $player, bool $async): void {}
}