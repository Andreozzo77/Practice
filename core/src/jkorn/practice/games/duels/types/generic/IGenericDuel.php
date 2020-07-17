<?php

declare(strict_types=1);

namespace jkorn\practice\games\duels\types\generic;


use jkorn\practice\games\IGame;
use jkorn\practice\games\misc\ISpectatorGame;

interface IGenericDuel extends IGame, ISpectatorGame
{
    /**
     * @return int
     *
     * Gets the game's id.
     */
    public function getID(): int;

    /**
     * @param callable $callback - The callback used, requires a player parameter.
     *      Ex: broadcast(function(Player $player) {});
     *
     * Broadcasts something to everyone in the game based on a callback.
     */
    public function broadcastGlobal(callable $callback): void;
}