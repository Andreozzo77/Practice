<?php

declare(strict_types=1);

namespace jkorn\practice\kits;


use jkorn\practice\misc\ISaved;
use pocketmine\Server;
use jkorn\practice\misc\AbstractManager;
use jkorn\practice\misc\PracticeAsyncTask;
use jkorn\practice\PracticeCore;

class KitManager extends AbstractManager
{

    /** @var string */
    private $kitDirectory;
    /** @var SavedKit[] */
    private $kits;

    public function __construct(PracticeCore $core)
    {
        $this->kitDirectory = $core->getDataFolder() . "kits/";
        $this->kits = [];

        parent::__construct(false);
    }

    /**
     * Loads the data needed for the manager.
     *
     * @param bool $async
     */
    protected function load(bool $async = false): void
    {
        if($async)
        {
            $this->server->getAsyncPool()->submitTask(new class($this->kitDirectory) extends PracticeAsyncTask {

                /** @var string */
                private $kitDirectory;

                public function __construct(string $kitDirectory)
                {
                    $this->kitDirectory = $kitDirectory;
                }

                /**
                 * Actions to execute when run
                 *
                 * @return void
                 */
                public function onRun()
                {
                    if(!is_dir($this->kitDirectory)) {
                        mkdir($this->kitDirectory);
                        $this->setResult(["kits" => []]);
                        return;
                    }

                    $files = scandir($this->kitDirectory);
                    if(count($files) <= 0)
                    {
                        $this->setResult(["kits" => []]);
                        return;
                    }

                    $kits = [];
                    foreach($files as $file) {
                        if(strpos($file, ".json") === false) {
                            continue;
                        }
                        $contents = json_decode(file_get_contents($this->kitDirectory . "/" . $file), true);
                        $name = str_replace(".json", "", $file);
                        $kits[$name] = $contents;
                    }

                    $this->setResult(["kits" => $kits]);
                }

                /**
                 * @param Server $server
                 * Called if the plugin is enabled.
                 */
                public function doComplete(Server $server): void
                {
                    $results = $this->getResult();
                    if($results !== null && isset($results["kits"]))
                    {
                        $kits = $results["kits"];
                        if(count($kits) <= 0) {
                            return;
                        }

                        PracticeCore::getKitManager()->postLoad($kits);
                    }
                }
            });
            return;
        }

        // This section runs when its not async.

        if(!is_dir($this->kitDirectory))
        {
            mkdir($this->kitDirectory);
            return;
        }

        $files = scandir($this->kitDirectory);
        if(count($files) <= 0)
        {
            return;
        }

        $kits = [];
        foreach($files as $file) {
            if(strpos($file, ".json") === false) {
                continue;
            }
            $contents = json_decode(file_get_contents($this->kitDirectory . "/" . $file), true);
            $name = str_replace(".json", "", $file);
            $kits[$name] = $contents;
        }

        $this->postLoad($kits);
    }

    /**
     * @param $data
     *
     * Loads the kits accordingly.
     */
    public function postLoad($data): void
    {
        foreach($data as $kitName => $kitData) {
            $kit = SavedKit::decode((string)$kitName, $kitData);
            if($kit instanceof SavedKit) {
                $this->kits[strtolower($kit->getName())] = $kit;
            }
        }
    }

    /**
     * Saves the data from the manager.
     *
     * @param bool $async
     */
    public function save(bool $async = false): void
    {
        if($async) {
            return;
        }

        foreach($this->kits as $localized => $kit) {

            if(!$kit instanceof ISaved)
            {
                continue;
            }

            $file = $this->kitDirectory . "{$kit->getName()}.json";
            if(!file_exists($file)) {
                $file = fopen($file, "w");
                fclose($file);
            }

            file_put_contents(
                $file,
                json_encode($kit->export())
            );
        }
    }

    /**
     * @param $kit
     *
     * Deletes the kit from the list.
     */
    public function delete($kit): void
    {
        if(!$kit instanceof SavedKit && !is_string($kit)) {
            return;
        }

        $name = $kit instanceof SavedKit ? $kit->getName() : $kit;
        $lowercase = strtolower($name);
        if(isset($this->kits[$lowercase])) {
            $this->handleDelete($lowercase);
        }
    }

    /**
     * @param SavedKit $kit
     * @return bool
     *
     * Adds the kit to the list.
     */
    public function add(SavedKit $kit): bool
    {
        if(isset($this->kits[$localized = strtolower($kit->getName())]))
        {
            return false;
        }

        $this->kits[$localized] = $kit;
        return true;
    }

    /**
     * @param string|null $kit
     * @return SavedKit|null
     *
     * Gets the kit from the list.
     */
    public function get(?string $kit): ?SavedKit
    {
        if($kit === null)
        {
            return null;
        }

        $localized = strtolower($kit);
        if(isset($this->kits[$localized])) {
            return $this->kits[$localized];
        }
        return null;
    }

    /**
     * @return array|SavedKit[]
     *
     * Lists all kits.
     */
    public function getAll()
    {
        return $this->kits;
    }

    /**
     * @param string $localized
     *
     * Called when the kit manager deletes a kit via the save
     * function non async.
     */
    private function handleDelete(string &$localized): void
    {
        if(!isset($this->kits[$localized]))
        {
            return;
        }

        $kit = $this->kits[$localized];
        $file = $this->kitDirectory . "{$kit->getName()}.json";

        if(file_exists($file)) {
            unlink($file);
        }

        // TODO: Remove kit from all arenas.

        unset($this->kits[$localized]);
    }
}