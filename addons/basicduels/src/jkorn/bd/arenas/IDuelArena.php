<?php

declare(strict_types=1);

namespace jkorn\bd\arenas;


use pocketmine\math\Vector3;

interface IDuelArena
{
    /**
     * @return Vector3
     *
     * Gets the first player position.
     */
    public function getP1StartPosition(): Vector3;

    /**
     * @return Vector3
     *
     * Gets the second player position.
     */
    public function getP2StartPosition(): Vector3;

    /**
     * @param Vector3 $position
     * @return bool
     *
     * Determines whether or not the player is in the arena.
     */
    public function isWithinArena(Vector3 $position): bool;
}