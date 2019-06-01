<?php

namespace pawarenessc\kit;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\tile\Tile;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Item;
use pocketmine\inventory\PlayerInventory;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

use metowa1227\moneysystem\api\core\API;
use MixCoinSystem\MixCoinSystem;

class Main extends pluginBase implements Listener
{
    public function onEnable()
    {
        $this->getLogger()->info("=========================");
        $this->getLogger()->info("Kitを読み込みました");
        $this->getLogger()->info("v7.5.1");
        $this->getLogger()->info("=========================");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->system = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        $this->plugin = $this->getServer()->getPluginManager()->getPlugin("LevelSystem");
        
        

 		$this->config = new Config($this->getDataFolder()."Setup.yml", Config::YAML, 
 			([
			"plugin" => "EconomyAPI",
			]));
        
        $this->names = new Config($this->getDataFolder()."name.yml", Config::YAML);
        
        $this->kit = new Config($this->getDataFolder() . "Kit.yml", Config::YAML,
		array(
		"新規キット"=>array(
		
		"アイテム"=>array(
		"アイテム1"=>"0:0:0",
		"アイテム2"=>"0:0:0"
		),
		
		
		"装備"=>array(
		"ヘルメット"=> 0,
		"チェストプレート"=> 0,
		"レギンス"=> 0,
		"ブーツ"=> 0
		),
		
		"エフェクト"=>array(
		"エフェクト1"=>"0:0:0",
		"エフェクト2"=>"0:0:0"
		),
		
		"最大体力"=> 20,
		"体力"=> 20,
		"レベル"=> 5,
		"お金"=> 100
		)));
    }
    
    public function onJoin(PlayerJoinEvent $event)
    {
    	$player = $event->getPlayer();
		$name = $player->getName();
		$this->names->set($name ,"");
		$this->names->save();
	}
    
    public function onChangeEvent(SignChangeEvent $event)
    {
        $player = $event->getPlayer();
        $name = $player->getName();
        if ($event->getLine(0) == "kitsign") {
        	if ($this->kit->exists($event->getLine(1))) {
            	if ($player->isOp()) {
                $x = $event->getBlock()->x;
                $y = $event->getBlock()->y;
                $z = $event->getBlock()->z;
                $kit = $event->getline(1);
                $data = $this->kit->getAll()[$kit];
                
                $event->setLine(0, "§l§cKIT§4SHOP");
                $event->setLine(1, $kit);
                $event->setLine(2, "§6必要金額§f:§l" .$data["お金"]);
                $event->setLine(3, "§a必要レベル§f:§l" .$data["レベル"]);
                $player->sendMessage("キット看板を生成しました キット名:{$kit}");
            } else {
                $player->sendMessage("権限がありません");
            	}
        	} else {
        	$player->sendMessage("§cそのキットは登録されていないようです。");
        	}
    	}
	}
    public function onTouch(PlayerInteractEvent $event)
    {
        $blockid = $event->getBlock()->getID();
        if ($blockid == 63 or $blockid == 68 or $blockid == 323) {
            $x = $event->getBlock()->x;
            $y = $event->getBlock()->y;
            $z = $event->getBlock()->z;
            $player = $event->getPlayer();
            $name = $player->getName();
            $level = $event->getBlock()->getLevel();
            $pos = new Vector3($x, $y, $z);
			$sign = $level->getTile($pos);
				if($sign instanceof Sign){
				}else{
					$shop = $sign->getLine(0);
					$kit = $sign->getLine(1);
					if($player->isSneaking()){
					
					$this->sendKitUI($player, $kit);
					
					}else{
					
					if($shop == "§l§cKIT§4SHOP"){
						if ( $this->kit->exists($kit) ) {
						$data = $this->kit->getAll()[$kit];
						$money = $this->getMoney($player);
						$level = $this->plugin->getLevel($name);
						
						if($level >= $data["レベル"]){
							if($money >= $data["お金"]){
								$this->setKitA($player ,$kit);
								$player->sendMessage("{$kit}を§6購入しました");
							
						}else{
						$player->sendMessage("§cお金が足りません！");
        				}
        				
        			}else{
        			$player->sendMessage("§cレベルが足りません！");
        			}
    			}else{
    			$player->sendMessage("§cこのキット名は存在しません！");
    			}
					}
				}
			}
		}
	}
	
	public function setKitA($player ,$kit)
	{
		
		$name = $player->getName();
		$data = $this->kit->getAll()[$kit];
		
		$player->getInventory()->clearAll();
		$player->removeAllEffects();
		
		$armor = $player->getArmorInventory();
        $armor->setHelmet(Item::get($data["装備"]["ヘルメット"],0,1));
		$armor->setChestplate(Item::get($data["装備"]["チェストプレート"],0,1));
		$armor->setLeggings(Item::get($data["装備"]["レギンス"],0,1));
		$armor->setBoots(Item::get($data["装備"]["ブーツ"],0,1));


		$this->names->set($name ,$kit);
		$this->names->save();
		
		$this->cutMoney($player, $data["お金"]);
								
		foreach($data["アイテム"] as $item){
		$item = explode(":",$item);
		$item = Item::get($item[0],$item[1],$item[2]);
		$player->getInventory()->addItem($item);
		}
								
		foreach($data["エフェクト"] as $effect){
		$effect = explode(":",$effect);
		if($effect[0] != 0){
		$player->addEffect(new EffectInstance(Effect::getEffect($effect[0]), $effect[1] * 20, $effect[2], true));
			}
		}
								
		$player->setMaxHealth($data["最大体力"]);
		if($data["体力"] <= $data["最大体力"]){
		$player->setHealth($data["体力"]);
		}else{
		$player->setHealth($data["最大体力"]);
		}
	}
	
	
	
	public function setKitB($name)
	{
		if($this->names->exists($name)) {
			$kit = $this->names->get($name);
			$this->setKitA($name ,$kit);
		}
	}
	
	
	public function getMoney($player)
	{
 	
 		$name = $player->getName();
		$plugin = $this->config->get("plugin");
 	
 		if($plugin == "MoneySystem"){
 		return API::getInstance()->get($player);
 	
 		}elseif($plugin == "EconomyAPI"){
		return $this->system->mymoney($name);
 	
 		}elseif($plugin == "MixCoinSystem"){
 	 	return MixCoinSystem::getInstance()->GetCoin($name);
 		}	
 	}
 	
 	public function cutMoney($player ,$money)
 	{	
 		$name = $player->getName();
		$plugin = $this->config->get("plugin");

		if($plugin == "MoneySystem"){
 		API::getInstance()->reduce($player, $money);
 	
 		}elseif($plugin == "EconomyAPI"){
		$this->system->reduceMoney($name, $money);

 		}elseif($plugin == "MixCoinSystem"){
 	 	MixCoinSystem::getInstance()->MinusCoin($player,$Coin);
 		}
 	}
 	
 	
 	public function sendKitUI($player ,$kit)
 	{
 		$name = $player->getName();
 		$data = $this->kit->getAll()[$kit];
 		$max = $data["最大体力"];
 		$set = $data["体力"];
 		
 		$maxh = $max * 2;
 		$seth = $set * 2;
 		
 		$money = $data["お金"];
 		$level = $data["レベル"];
 		
 		$helmet = $this->getArmorName(0 ,$data["装備"]["ヘルメット"]);
 		$chest = $this->getArmorName(1 ,$data["装備"]["チェストプレート"]);
 		$leggings = $this->getArmorName(2 ,$data["装備"]["レギンス"]);
 		$boots = $this->getArmorName(3 ,$data["装備"]["ブーツ"]);
 		
 		$fdata = [
				"type" => "custom_form",
				"title" => "§lキットの確認",
				"content" => [
					[
						"type" => "label",
						"text" => "§l§aキット名: §r{$kit}"
					],
					[
						"type" => "label",
						"text" => "最大体力: §c{$max}§r\nハート数: §c{$maxh}"
					],
					[
						"type" => "label",
						"text" => "体力: §d{$set}§r\nハート数: §d{$seth}"
					],
					[
						"type" => "label",
						"text" => "§6必要金額:§r§l{$money}"
					],
					[
						"type" => "label",
						"text" => "§b必要レベル:§r§lLv.{$level}"
					],
					[
						"type" => "label",
						"text" => "§l§6防具:§r\nヘルメット:{$helmet}\nチェストプレート:{$chest}\nレギンス:{$leggings}\nブーツ:{$boots}"
					]
				]
			];
					/*[
						"type" => "label",
						"text" => ""
				]
			];*/
			$this->createWindow($player, $fdata, 456200);
	}
	
	/*
	0 頭
	1 プレート
	2 レギンス
	3 ブーツ
	*/
	
	public function getArmorName($type ,$id)
	{
		switch($type)
		{
			case 0: // 頭
				
				switch($id)
				{
					case 0:
					return "";
					break;
					
					case 298:
					return "§4革§r";
					break;
					
					case 302:
					return "§fチェーン§r";
					break;
					
					case 306:
					return "§f鉄§r";
					break;
					
					case 310;
					return "§bダイア§r";
					break;
					
					case 314;
					return "§e金§r";
					break;
					
					default:
					return "null";
					break;
				}
			
			case 1: // プレート
			
				switch($id)
				{
					case 0:
					return "";
					break;
					
					case 299:
					return "§4革§r";
					break;
					
					case 303:
					return "§fチェーン§r";
					break;
					
					case 307:
					return "§f鉄§r";
					break;
					
					case 311;
					return "§bダイア§r";
					break;
					
					case 315;
					return "§e金§r";
					break;
					
					default:
					return "null";
					break;
				}
			
			case 2: // レギンス
			
				switch($id)
				{
					case 0:
					return "";
					break;
					
					case 300:
					return "§4革§r";
					break;
					
					case 304:
					return "§fチェーン§r";
					break;
					
					case 308:
					return "§f鉄§r";
					break;
					
					case 312;
					return "§bダイア§r";
					break;
					
					case 316;
					return "§e金§r";
					break;
					
					default:
					return "null";
					break;
				}
			
			case 3: // ブーツ
			
				switch($id)
				{
					case 0:
					return "";
					break;
					
					case 301:
					return "§4革§r";
					break;
					
					case 305:
					return "§fチェーン§r";
					break;
					
					case 309:
					return "§f鉄§r";
					break;
					
					case 313;
					return "§bダイア§r";
					break;
					
					case 317;
					return "§e金§r";
					break;
					
					default:
					return "null";
					break;
				}
		}
	}
	
	public function createWindow(Player $player, $data, int $id){
		$pk = new ModalFormRequestPacket();
		$pk->formId = $id;
		$pk->formData = json_encode($data, JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE);
		$player->dataPacket($pk);
	}
}
