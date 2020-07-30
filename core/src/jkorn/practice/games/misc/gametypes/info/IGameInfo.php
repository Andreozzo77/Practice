<?php

declare(strict_types=1);

namespace jkorn\practice\games\misc\gametypes\info;

/**
 * Interface IGameInfo
 * @package jkorn\practice\games\misc
 *
 * The base game type class, used for awaiting information.
 */
interface IGameInfo
{

    /**
     * @return string
     *
     * Gets the texture of the game type.
     */
    public function getTexture(): string;

    /**
     * @return string
     *
     * Gets the localized name of the game type.
     */
    public function getLocalizedName(): string;

    /**
     * @return string
     *
     * Gets the display name of the game type.
     */
    public function getDisplayName(): string;

    /**
     * @param $object
     * @return bool
     *
     * Determines if a game type is equivalent.
     */
    public function equals($object): bool;
}