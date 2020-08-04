<?php
/**
 * Created by PhpStorm.
 * User: jkorn2324
 * Date: 2020-07-31
 * Time: 19:35
 */

declare(strict_types=1);

namespace jkorn\practice\forms\internal\types\kits\edit;


use jkorn\practice\forms\internal\InternalForm;
use jkorn\practice\kits\IKit;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class EditKitItems extends InternalForm
{

    /**
     * @return string
     *
     * Gets the localized name of the practice form.
     */
    public function getLocalizedName(): string
    {
        return self::EDIT_KIT_ITEMS;
    }

    /**
     * @param Player $player
     * @param mixed ...$args
     *
     * Called when the display method first occurs.
     */
    protected function onDisplay(Player $player, ...$args): void
    {
        if
        (
            !isset($args[0])
            || ($kit = $args[0]) === null
            || !$kit instanceof IKit
        )
        {
            return;
        }

        //TODO
    }

    /**
     * @param Player $player
     * @return bool
     *
     * Tests the form's permissions to see if the player can use it.
     */
    protected function testPermission(Player $player): bool
    {
        // TODO: Implement testPermission() method.
        return true;
    }
}