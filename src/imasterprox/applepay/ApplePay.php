<?php

namespace imasterprox\applepay;

use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Position;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class ApplePay extends PluginBase implements Listener{

	public static string $prefix = "§l§f[애플페이] §r§7";

	private Config $config;

	private array $commands;

	private Config $commandsConfig;

	public static function positionHash(Position $pos){
		return $pos->getLevel()->getFolderName() . ":" . $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ();
	}

	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->saveResource("config.yml");

		$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);

		$this->commandsConfig = new Config($this->getDataFolder() . "config.yml", Config::YAML);

		$this->commands = $this->commandsConfig->getAll();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onDisable(){
		$this->commandsConfig->setAll($this->commands);
		$this->commandsConfig->save();
	}

	public function setCommandSign(Position $pos, string $command){
		$this->commands[self::positionHash($pos)] = $command;
	}

	public function getCommandSign(Position $pos){
		return $this->commands[self::positionHash($pos)] ?? null;
	}

	public function removeCommandSign(Position $pos){
		unset($this->commands[self::positionHash($pos)]);
	}

	/**
	 * @ignoreCancelled false
	 * @priority        MONITOR
	 */
	public function handlePlayerInteract(PlayerInteractEvent $event){
		if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			if($event->getBlock()->getId() === Block::SIGN_POST || $event->getBlock()->getId() === Block::WALL_SIGN){
				$commandLine = $this->getCommandSign($event->getBlock());
				if($commandLine !== null){
					if(!$event->getPlayer()->hasPermission("commandsign.use")){
						return;
					}
					$commandEv = new PlayerCommandPreprocessEvent($event->getPlayer(), "/pay " . $commandLine);
					$this->getServer()->getPluginManager()->callEvent($commandEv);
					if($commandEv->isCancelled()){
						return;
					}
					$event->setCancelled();
					$this->getServer()->dispatchCommand($event->getPlayer(), substr($commandEv->getMessage(), 1));
				}
			}
		}
	}

	/**
	 * @ignoreCancelled true
	 * @priority        MONITOR
	 */
	public function handleBlockBreak(BlockBreakEvent $event){
		if($this->getCommandSign($event->getBlock()) !== null){
			$this->removeCommandSign($event->getBlock());
			$event->getPlayer()->sendMessage(ApplePay::$prefix . "Apple Pay를 삭제하였습니다.");
		}
	}

	/**
	 * @ignoreCancelled true
	 * @priority        HIGH
	 */
	public function handleSignChange(SignChangeEvent $event){
		$lines = $event->getLines();
		if(array_shift($lines) === "[pay]"){
			$commandLine = implode($lines);
			$newLines = explode("\n", str_ireplace("{COMMAND}", (trim($commandLine) == "") ? "§d(유저이름) (금액)" : $commandLine, $this->config->get("commandsign-format", "§l§fApple Pay §r§7\n{COMMAND}")));
			for($i = 0; $i < 4; $i++){
				$event->setLine($i, $newLines[$i] ?? "");
			}

			$this->setCommandSign($event->getBlock(), $commandLine);
		}
	}
}
