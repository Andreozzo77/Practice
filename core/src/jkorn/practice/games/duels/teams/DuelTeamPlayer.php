<?php

declare(strict_types=1);

namespace jkorn\practice\games\duels\teams;


use jkorn\practice\games\duels\player\DuelPlayer;

class DuelTeamPlayer extends DuelPlayer
{

    /** @var bool */
    private $spectator = false;

    /**
     * @return bool
     *
     * Determines if the duel team player is now spectating.
     */
    public function isSpectator(): bool
    {
        return $this->spectator;
    }

    /**
     * Sets the player as a spectator.
     */
    public function setSpectator(): void
    {
        $this->spectator = true;
    }
}