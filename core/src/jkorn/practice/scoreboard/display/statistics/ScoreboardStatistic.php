<?php

declare(strict_types=1);

namespace jkorn\practice\scoreboard\display\statistics;


use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use jkorn\practice\arenas\types\ffa\FFAArena;
use jkorn\practice\player\PracticePlayer;

class ScoreboardStatistic
{

    /** @var string */
    protected $localizedName;
    /** @var callable */
    protected $callable;

    /** @var mixed */
    protected $value;

    /** @var Server */
    protected $server;

    public function __construct(string $localizedName, callable $callable)
    {
        $this->localizedName = $localizedName;
        $this->callable = $callable;

        $this->server = Server::getInstance();
    }

    /**
     * @return string
     *
     * Gets the localized name.
     */
    public function getLocalized(): string
    {
        return $this->localizedName;
    }

    /**
     * @param Player $player - The player input.
     * @return mixed
     *
     * Updates and gets the value of the statistic.
     */
    public function getValue(Player $player)
    {
        $callable = $this->callable;
        return $callable($player, $this->server);
    }

    // -------------------------------------------------------------------------------

    const STATISTIC_KILLS = "kills";
    const STATISTIC_DEATHS = "deaths";
    const STATISTIC_CPS = "cps";
    const STATISTIC_ONLINE = "online";
    const STATISTIC_IN_QUEUES = "in.queues";
    const STATISTIC_IN_FIGHTS = "in.fights";
    const STATISTIC_PING = "ping";
    const STATISTIC_NAME = "name";
    const STATISTIC_OS = "os";
    const STATISTIC_RANK = "rank"; // TODO: Rank
    const STATISTIC_FFA_ARENA = "ffa.arena"; // TODO: Move to ffa arena.
    const STATISTIC_FFA_ARENA_PLAYERS = "ffa.arena.players";
    const STATISTIC_OPPONENT = "opponent";
    const STATISTIC_OPPONENT_CPS = "opponent.cps";
    const STATISTIC_OPPONENT_PING = "opponent.ping";
    const STATISTIC_DUEL_ARENA = "duel.arena";
    const STATISTIC_DURATION = "duration";
    const STATISTIC_SPECTATORS = "spectators";
    const STATISTIC_KIT = "kit";
    const STATISTIC_RANKED = "ranked";

    /** @var ScoreboardStatistic[] */
    private static $statistics = [];

    /**
     * Initializes the default statistics.
     */
    public static function init(): void
    {
        // Registers the kills statistic.
        self::registerStatistic(new ScoreboardStatistic(
            self::STATISTIC_KILLS,

            function(Player $player, Server $server)
            {
                if($player instanceof PracticePlayer)
                {
                    $statistics = $player->getStatsInfo();
                    if($statistics !== null)
                    {
                        return $statistics->getKills();
                    }
                }

                return 0;
            })
        );

        // Registers the deaths statistic.
        self::registerStatistic(new ScoreboardStatistic(
            self::STATISTIC_DEATHS,
            function(Player $player, Server $server)
            {
                if($player instanceof PracticePlayer)
                {
                    $statistics = $player->getStatsInfo();
                    if($statistics !== null)
                    {
                        return $statistics->getDeaths();
                    }
                }

                return 0;
            }
        ));

        // Registers the cps statistic.
        self::registerStatistic(new ScoreboardStatistic(
            self::STATISTIC_CPS,
            function(Player $player, Server $server)
            {
                if($player instanceof PracticePlayer)
                {
                    return $player->getClicksInfo()->getCps();
                }
                return 0;
            }
        ));

        // Registers the online statistic.
        self::registerStatistic(new ScoreboardStatistic(
            self::STATISTIC_ONLINE,
            function(Player $player, Server $server)
            {
                return count($server->getOnlinePlayers());
            }
        ));

        // Registers the in-queues statistic.
        self::registerStatistic(new ScoreboardStatistic(
            self::STATISTIC_IN_QUEUES,
            function(Player $player, Server $server)
            {
                // TODO: In-Queues statistic.
                return 0;
            }
        ));

        // Registers the in-fights statistic.
        self::registerStatistic(new ScoreboardStatistic(
            self::STATISTIC_IN_FIGHTS,
            function(Player $player, Server $server)
            {
                // TODO: In-Fights statistic.
                return 0;
            }
        ));

        // Registers the ping statistic.
        self::registerStatistic(new ScoreboardStatistic(
            self::STATISTIC_PING,
            function(Player $player, Server $server)
            {
                return $player->getPing();
            }
        ));

        // Register the display name statistic.
        self::registerStatistic(new ScoreboardStatistic(
            self::STATISTIC_NAME,
            function(Player $player, Server $server)
            {
                return $player->getDisplayName();
            }
        ));

        // Registers the statistic.
        self::registerStatistic(new ScoreboardStatistic(
            self::STATISTIC_OS,
            function(Player $player, Server $server)
            {
                if($player instanceof PracticePlayer)
                {
                    $clientInfo = $player->getClientInfo();
                    if($clientInfo !== null)
                    {
                        return $clientInfo->getDeviceOS(true);
                    }
                }
                return TextFormat::RED . "[Unknown]" . TextFormat::RESET;
            }
        ));

        // Registers the arena name to the player.
        self::registerStatistic(new FFAScoreboardStatistic(
            self::STATISTIC_FFA_ARENA,
            function(Player $player, Server $server, $arena)
            {
                if($arena instanceof FFAArena)
                {
                    return $arena->getName();
                }

                return "[Unknown]";
            }
        ));

        // Registers the arena name to the player.
        self::registerStatistic(new FFAScoreboardStatistic(
            self::STATISTIC_FFA_ARENA_PLAYERS,
            function(Player $player, Server $server, $arena)
            {
                if($arena instanceof FFAArena)
                {
                    return $arena->getPlayers();
                }

                return 0;
            }
        ));

        // Registers the opponent name.
        self::registerStatistic(new DuelScoreboardStatistic(
            self::STATISTIC_OPPONENT,
            function(Player $player, Server $server, $duel)
            {
                // TODO
                return "";
            }
        ));

        // Registers the opponent cps.
        self::registerStatistic(new DuelScoreboardStatistic(
            self::STATISTIC_OPPONENT_CPS,
            function(Player $player, Server $server, $duel)
            {
                // TODO
                return "";
            }
        ));

        // Registers the opponent ping.
        self::registerStatistic(new DuelScoreboardStatistic(
            self::STATISTIC_OPPONENT_PING,
            function(Player $player, Server $server, $duel)
            {
                // TODO
                return 0;
            }
        ));

        // Registers the duel arena name.
        self::registerStatistic(new DuelScoreboardStatistic(
            self::STATISTIC_DUEL_ARENA,
            function(Player $player, Server $server, $duel)
            {
                // TODO
                return "";
            }
        ));

        // Registers the duration statistic.
        self::registerStatistic(new DuelScoreboardStatistic(
            self::STATISTIC_DURATION,
            function(Player $player, Server $server, $duel)
            {
                return "00:00";
            }
        ));

        // Register the spectator statistic.
        self::registerStatistic(new DuelScoreboardStatistic(
            self::STATISTIC_SPECTATORS,
            function(Player $player, Server $server, $duel)
            {
                return 0;
            }
        ));

        // Registers the kit statistic.
        self::registerStatistic(new ScoreboardStatistic(
            self::STATISTIC_KIT,
            function(Player $player, Server $server)
            {
                if($player instanceof PracticePlayer && $player->isEquipped())
                {
                    return $player->getEquippedKit()->getName();
                }

                // TODO: If player is in a queue, get the kit the player is queued for.

                return TextFormat::RED . "[Unknown]" . TextFormat::RESET;
            }
        ));

        // Registers the ranked statistic.
        self::registerStatistic(new ScoreboardStatistic(
            self::STATISTIC_RANKED,
            function(Player $player, Server $server)
            {
                // TODO: Get ranked queue.
                return "";
            }
        ));
    }

    /**
     * @param ScoreboardStatistic $statistic
     * @param bool $override - Determines whether or not we want to override the statistic.
     *
     * Registers the statistic to the list of statistics.
     */
    public static function registerStatistic(ScoreboardStatistic $statistic, bool $override = false): void
    {
        if(isset(self::$statistics[$statistic->getLocalized()]) && !$override)
        {
            return;
        }

        self::$statistics[$statistic->getLocalized()] = $statistic;
    }

    /**
     * @param string $localized
     *
     * Unregisters the statistic.
     */
    public static function unregisterStatistic(string $localized): void
    {
        if(isset(self::$statistics[$localized]))
        {
            unset(self::$statistics[$localized]);
        }
    }

    /**
     * @param Player $player - The input player.
     * @param string $message
     *
     * Converts the message containing the statistics.
     */
    public static function convert(Player $player, string &$message): void
    {
        foreach(self::$statistics as $localized => $statistic)
        {
            $statisticVariable = "{{$localized}}";
            if(strpos($message, $statisticVariable) !== 0)
            {
                $message = str_replace($statisticVariable, $statistic->getValue($player), $message);
            }
        }
    }

    /**
     * @param string $message
     * @return bool
     *
     * Determines if the message contains statistics.
     */
    public static function containsStatistics(string &$message): bool
    {
        foreach (self::$statistics as $localized => $statistic)
        {
            $statisticVariable = "{$localized}";
            if(strpos($message, $statisticVariable) !== 0)
            {
                return true;
            }
        }
        return false;
    }
}