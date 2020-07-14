<?php

declare(strict_types=1);

namespace practice;


use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;
use practice\misc\AbstractManager;
use practice\player\PracticePlayer;

class PracticeUtil
{

    // Color array constant.
    const COLOR_ARRAY = [
        "{BLUE}" => TextFormat::BLUE,
        "{GREEN}" => TextFormat::GREEN,
        "{RED}" => TextFormat::RED,
        "{DARK_RED}" => TextFormat::DARK_RED,
        "{DARK_BLUE}" => TextFormat::DARK_BLUE,
        "{DARK_AQUA}" => TextFormat::DARK_AQUA,
        "{DARK_GREEN}" => TextFormat::DARK_GREEN,
        "{GOLD}" => TextFormat::GOLD,
        "{GRAY}" => TextFormat::GRAY,
        "{DARK_GRAY}" => TextFormat::DARK_GRAY,
        "{DARK_PURPLE}" => TextFormat::DARK_PURPLE,
        "{LIGHT_PURPLE}" => TextFormat::LIGHT_PURPLE,
        "{RESET}" => TextFormat::RESET,
        "{YELLOW}" => TextFormat::YELLOW,
        "{AQUA}" => TextFormat::AQUA,
        "{BOLD}" => TextFormat::BOLD,
        "{WHITE}" => TextFormat::WHITE,
        "{ITALIC}" => TextFormat::ITALIC,
        "{UNDERLINE}" => TextFormat::UNDERLINE
    ];

    /**
     * @param string|int $index - Int or string.
     * @return int|string
     *
     * Converts the armor index based on its type.
     */
    public static function convertArmorIndex($index)
    {
        if(is_string($index))
        {
            switch(strtolower($index))
            {
                case "boots":
                    return 3;
                case "leggings":
                    return 2;
                case "chestplate":
                case "chest":
                    return 1;
                case "helmet":
                    return 0;
            }

            return 0;
        }

        switch($index % 4)
        {
            case 0:
                return "helmet";
            case 1:
                return "chestplate";
            case 2:
                return "leggings";
            case 3:
                return "boots";
        }

        return 0;
    }

    /**
     * @param Item $item
     * @return array
     *
     * Converts an item to an array.
     */
    public static function itemToArr(Item $item): array
    {
        $output = [
            "id" => $item->getId(),
            "meta" => $item->getDamage(),
            "count" => $item->getCount()
        ];

        if($item->hasEnchantments())
        {
            $enchantments = $item->getEnchantments();
            $inputEnchantments = [];
            foreach($enchantments as $enchantment)
            {
                $inputEnchantments[] = [
                    "id" => $enchantment->getId(),
                    "level" => $enchantment->getLevel()
                ];
            }

            $output["enchants"] = $inputEnchantments;
        }

        if($item->hasCustomName())
        {
            $output["customName"] = $item->getCustomName();
        }

        return $output;
    }

    /**
     * @param array $input
     * @return Item|null
     *
     * Converts an array of data to an item.
     */
    public static function arrToItem(array $input): ?Item
    {
        if(!isset($input["id"], $input["meta"], $input["count"]))
        {
            return null;
        }

        $item = Item::get($input["id"], $input["meta"], $input["count"]);
        if(isset($input["customName"]))
        {
            $item->setCustomName($input["customName"]);
        }

        if(isset($input["enchants"]))
        {
            $enchantments = $input["enchants"];
            foreach($enchantments as $enchantment)
            {
                if(!isset($enchantment["id"], $enchantment["level"]))
                {
                    continue;
                }

                $item->addEnchantment(new EnchantmentInstance(
                    Enchantment::getEnchantment($enchantment["id"]),
                    $enchantment["level"]
                ));
            }
        }

        return $item;
    }

    /**
     * @param EffectInstance $instance
     * @param int $duration
     * @return array
     *
     * Converts an effect instance to an array.
     */
    public static function effectToArr(EffectInstance $instance, int $duration = 30 * 60 * 20): array
    {
        return [
            "id" => $instance->getId(),
            "amplifier" => $instance->getAmplifier(),
            "duration" => $duration
        ];
    }

    /**
     * @param array $input
     * @return EffectInstance|null
     *
     * Converts an array to an effect instance.
     */
    public static function arrToEffect(array $input): ?EffectInstance
    {
        if(!isset($input["id"], $input["amplifier"], $input["duration"]))
        {
            return null;
        }

        return new EffectInstance(
            Effect::getEffect($input["id"]),
            $input["duration"],
            $input["amplifier"]
        );
    }

    /**
     * @param string $message - The address to the message.
     *
     * Converts the message according to its colors.
     */
    public static function convertMessageColors(string &$message): void
    {
        foreach(self::COLOR_ARRAY as $color => $value)
        {
            if(strpos($message, $color) !== false)
            {
                $message = str_replace($color, $value, $message);
            }
        }
    }

    /**
     * @param $level1 - The first level.
     * @param $level2 - The second level.
     * @return bool - Return true if equivalent, false otherwise.
     *
     * Determines if the levels are equivalent.
     */
    public static function areLevelsEqual($level1, $level2): bool
    {
        if(!$level1 instanceof Level && !is_string($level1))
        {
            return false;
        }

        if(!$level2 instanceof Level && !is_string($level2))
        {
            return false;
        }

        if($level1 instanceof Level && $level2 instanceof Level)
        {
            return $level1->getId() === $level2->getId();
        }

        $level2Name = $level2 instanceof Level ? $level2->getName() : $level2;
        $level1Name = $level1 instanceof Level ? $level1->getName() : $level1;

        return $level1Name === $level2Name;
    }

    /**
     * @param Vector3|null $vec3 - The input vector3.
     * @return array|null
     *
     * Converts the vector3 to an array.
     */
    public static function vec3ToArr(?Vector3 $vec3): ?array
    {
        if($vec3 == null)
        {
            return null;
        }

        $output = [
            "x" => $vec3->x,
            "y" => $vec3->y,
            "z" => $vec3->z,
        ];

        if($vec3 instanceof Location)
        {
            $output["pitch"] = $vec3->pitch;
            $output["yaw"] = $vec3->yaw;
        }

        return $output;
    }

    /**
     * @param $input - The input array.
     * @return Vector3|null
     *
     * Converts an array input to a Vector3.
     */
    public static function arrToVec3($input): ?Vector3
    {
        if(is_array($input) && isset($input["x"], $input["y"], $input["z"]))
        {
            if(isset($input["pitch"], $input["yaw"]))
            {
                return new Location($input["x"], $input["y"], $input["z"], $input["yaw"], $input["pitch"]);
            }

            return new Vector3($input["x"], $input["y"], $input["z"]);
        }

        return null;
    }

    /**
     * @param string $uuid
     * @return Player|null
     *
     * Gets the player from their server id.
     */
    public static function getPlayerFromServerID(string $uuid): ?Player
    {
        $players = Server::getInstance()->getOnlinePlayers();

        foreach($players as $player)
        {
            if(!$player instanceof PracticePlayer)
            {
                continue;
            }

            $pUUID = $player->getServerID();
            if($pUUID->toString() === $uuid)
            {
                return $player;
            }
        }

        return null;
    }
}