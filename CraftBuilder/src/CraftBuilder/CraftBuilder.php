<?php
namespace CraftBuilder;
/*
CraftBuilder作者：KingLegend；
*/
use pocketmine\Player;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;

class CraftBuilder extends PluginBase implements Listener{
	private $status=array();	//记录玩家所创建的起点坐标和终点坐标
	private $clipboard=array();
	public function onEnable(){
		@mkdir($this->getDataFolder(),0777,true);
		@mkdir($this->getDataFolder()."Buildings/");
		$this->setting = new Config($this->getDataFolder()."Setting.yml", Config::YAML, array(
		"1表示以起点坐标为准的生成方式，2表示以玩家所在坐标的相对位置为准的生成方式，3表示以起点坐标和终点坐标中心为准的生成方式（默认方式为1）"=>1,
		"如果其他玩家加载了你创建的建筑文件，OP玩家和后台会收到此讯息"=>"这是一个未定义信息，OP可以输入/cb meg [内容]对此信息进行修改",
		"设置本服创建的建筑文件默认的联系方式"=>"这是一个未定义信息，OP可以输入/cb edit [内容]对此信息进行修改"
		));
		$this->ScratchFile = new Config($this->getDataFolder()."ScratchFile.yml", Config::YAML, array(
		));
		$this->pluginlog = new Config($this->getDataFolder()."Log.yml", Config::YAML, array(
		));
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info("-----------------------------------");
        $this->getLogger()->info("成功加载了CraftBuilder插件，以下是非官方对你输出的信息");
		$files = scandir($this->getDataFolder()."Buildings");
		foreach($files as $file){
			if($file != "." && $file != ".."){
				$filename = str_replace(".yml", "", $file);
				$this->cbinfo=new Config($this->getDataFolder()."Buildings/{$filename}.yml", Config::YAML, array());
				if($this->cbinfo->get("servermeg") !== "这是一个未定义信息，OP可以输入/cb meg [文件名] [内容]对此信息进行修改"){
					$this->getLogger()->info("[".$file.".yml给你发布的话]服务器描述".$this->cbinfo->get("servermeg")."关于我们：".$this->cbinfo->get("connect")."建筑文件描述".$this->cbinfo->get("describe"));
				}
			}
		}
		$this->getLogger()->info("-----------------------------------");

    }
	public function onJoin(PlayerJoinEvent $event){
		$player=$event->getPlayer();
		if($player->isOp()){
			$player->sendMessage("-----------------------------------");
			$player->sendMessage("此信息只会对拥有OP权限的玩家发布，以供查询");
			$files = scandir($this->getDataFolder()."Buildings");
			foreach($files as $file){
				if($file != "." && $file != ".."){
					$filename = str_replace(".yml", "", $file);
					$this->cbinfo=new Config($this->getDataFolder()."Buildings/{$filename}.yml", Config::YAML, array());
					if($this->cbinfo->get("servermeg") !== "这是一个未定义信息，OP可以输入/cb meg [文件名] [内容]对此信息进行修改"){
					$player->sendMessage("[".$file.".yml给你发布的话]服务器描述".$this->cbinfo->get("servermeg")."关于我们：".$this->cbinfo->get("connect")."建筑文件描述".$this->cbinfo->get("describe"));
					}
				}
			}
			$player->sendMessage("-----------------------------------");
		}
	}
	public function onDisable(){//清除临时文件的数据
		$this->ScratchFile->setAll(array());
		$this->ScratchFile->save();
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
	$name=$sender->getName();
	if(!$sender->isOp()){
		$sender->sendMessage("你不是OP，请获取命令权限后再使用这个指令");
	}
	switch($command->getName()){
		case "cb":
		if(isset($args[0])){
			switch($args[0]){
				case "1":
				$this->status[$name]["起点"]=[round($sender->getX()),round($sender->getY()),round($sender->getZ()),$sender->getLevel()->getFolderName()];
				$sender->sendMessage("起点坐标设置成功§7再点击一次设置终点坐标,用木斧点击方块设置生成地点\n§7(x=>{$x},y=>{$y},z=>{$z},level=>{$level})");
				break;
				case "2":
				$this->status[$name]["终点"]=[round($sender->getX()),round($sender->getY()),round($sender->getZ()),$sender->getLevel()->getFolderName()];
				$sender->sendMessage("终点坐标设置成功§7再点击一次设置起点坐标,用木斧点击方块设置生成地点\n§7(x=>{$x},y=>{$y},z=>{$z},level=>{$level})");
				break;
				case "new"://[命名文件]
				if(isset($args[1])){
					$file=$args[1];
					$起点=$this->status[$name]["起点"];
					$终点=$this->status[$name]["终点"];
					$selection=array("起点"=>[$起点[0],$起点[1],$起点[2],$起点[3]],"终点"=>[$终点[0],$终点[1],$终点[2],$终点[3]]);
					$send=$this->CreateFile($sender,$file,$selection);
					$sender->sendMessage($send);
					}else{
						$sender->sendMessage("输入[/cb new [命名文件]]来创建新的建筑文件");
					}
				break;
				case "del"://[命名文件]
				if(isset($args[1])){
					$file='Buildings/'.$args[1].'.yml';
					if(file_exists($this->getDataFolder().$file)){
						unlink($this->getDataFolder().$file);
						$sender->sendMessage("执行成功\n{$args[1]}.yml文件已经被删除");
					}else{
						$sender->sendMessage("执行错误\n{$args[1]}.yml文件不存在");
					}

				}else{
					$sender->sendMessage("输入[/cb del]来删除该建筑文件");
				}
				break;
				case "clearAll":
				$files = scandir($this->getDataFolder()."Buildings");
				
				foreach($files as $file){
					unlink($this->getDataFolder().'Buildings/'.$file.'.yml');
					$sender->sendMessage("执行删除{$file}.yml");
				}
				$sender->sendMessage("---Buildings路径下的所有建筑文件均已删除---");
				break;
				case "update"://[命名文件]
				if(isset($args[1])){
					if(!isset($this->status[$name]["起点"])){
						$sender->sendMessage("执行错误\n你没有设置起点坐标");
					}
					if(!isset($this->status[$name]["终点"])){
						$sender->sendMessage("执行错误\n你没有设置终点坐标");
					}
					$起点=$this->status[$name]["终点"];
					$终点=$this->status[$name]["终点"];
					$this->update=new Config($this->getDataFolder()."Buildings/{$file}.yml", Config::YAML, array());
					$b=$this->update->get("blocks");
					$sender->sendMessage("[第一步]执行成功\n§7成功获取{$file}.yml文件并选择了blocks区位");
					$selection=array("起点"=>[$起点[0],$起点[1],$起点[2],$起点[3]],"终点"=>[$终点[0],$终点[1],$终点[2],$终点[3]]);
					$blocks=$this->Selection2($sender,$selection);
					$this->update->set("blocks",$b);
					$v=$this->update->get("version");
					$this->update->set("version",$v+1);
					$this->update->save();
					$sender->sendMessage("执行成功\n§7成功将(开始坐标：x={$起点[0]},y={$起点[1]},z={$起点[2]},level={$起点[3]},结束坐标：x={$终点[0]},y={$终点[1]},z={$终点[2]},level={$终点[3]})区域内的方块数据保存在了{$file}.yml文件的blocks区位中了");
					
				}else{
					$sender->sendMessage("输入[/cb update]来更新该建筑文件");
				}
				break;
				case "list":
				$files = scandir($this->getDataFolder()."Buildings");
				$sender->sendMessage("---Buildings路径下的所有建筑文件---");
				foreach($files as $file){
					$sender->sendMessage($file);
				}
				break;
				case "newData":
				if(isset($this->status[$name])){
					if(!isset($this->status[$name]["起点"]) || $this->status[$name]["起点"][0]==$this->status[$name]["起点"][1] || $this->status[$name]["起点"][0]==$this->status[$name]["起点"][2]){
						$sender->sendMessage("执行错误\n起点坐标不存在");
						return false;
					}
					if(!isset($this->status[$name]["终点"]) || $this->status[$name]["终点"][0]==$this->status[$name]["终点"][1] || $this->status[$name]["终点"][0]==$this->status[$name]["终点"][2]){
						$sender->sendMessage("执行错误\n终点坐标不存在");
						return false;
					}
					if(!isset($this->status[$name]["生成"]) || $this->status[$name]["生成"][0]==$this->status[$name]["生成"][1] || $this->status[$name]["生成"][0]==$this->status[$name]["生成"][2]){
						$sender->sendMessage("执行错误\n生成坐标不存在");
						return false;
					}
					$起点=$this->status[$name]["起点"];
					$终点=$this->status[$name]["终点"];
					$生成=$this->status[$name]["生成"];
					$selection=array("起点"=>[$起点[0],$起点[1],$起点[2],$起点[3]],"终点"=>[$终点[0],$终点[1],$终点[2],$终点[3]]);
					$count = $this->countBlocks($selection, $startX, $startY, $startZ);
					$this->MakeNew($sender,array($起点[0],$起点[1],$起点[2],$起点[3]),
					array($终点[0],$终点[1],$终点[2],$终点[3])
					);
					$startX = min($selection["起点"][0], $selection["终点"][0]);
					$endX = max($selection["起点"][0], $selection["终点"][0]);
					$startY = min($selection["起点"][1], $selection["终点"][1]);
					$endY = max($selection["起点"][1], $selection["终点"][1]);
					$startZ = min($selection["起点"][2], $selection["终点"][2]);
					$endZ = max($selection["起点"][2], $selection["终点"][2]);
					if($this->setting->get("1表示以起点坐标为准的生成方式，2表示以玩家所在坐标的相对位置为准的生成方式，3表示以起点坐标和终点坐标中心为准的生成方式（默认方式为1）")==2){
											$offset = array($startX - $sender->getX() - 0.5, $startY - $sender->getY(), $startZ - $sender->getZ() - 0.5);

					}elseif($this->setting->get("1表示以起点坐标为准的生成方式，2表示以玩家所在坐标的相对位置为准的生成方式，3表示以起点坐标和终点坐标中心为准的生成方式（默认方式为1）")==1){
											$offset = array(-0.5, 0,-0.5);
					}elseif($this->setting->get("1表示以起点坐标为准的生成方式，2表示以玩家所在坐标的相对位置为准的生成方式，3表示以起点坐标和终点坐标中心为准的生成方式（默认方式为1）")==3){
											$offset = array(-0.5, 0, -0.5);
					}
					$blocks=$this->Selection($sender,$selection);
					$this->clipboard[$name]=array($offset, $blocks);
					$sender->sendMessage("§a创建成功,你可以输入[/cb create]来创造它");
				}else{
					$sender->sendMessage("你没有设置起点坐标或终点坐标");
				}
				break;
				case "create":
				
					if(isset($this->status[$name]["生成"])){//$sender->getX()
						if($this->setting->get("1表示以起点坐标为准的生成方式，2表示以玩家所在坐标的相对位置为准的生成方式，3表示以起点坐标和终点坐标中心为准的生成方式（默认方式为1）")==1){
							$pos=$this->status[$name]["生成"];
						}elseif($this->setting->get("1表示以起点坐标为准的生成方式，2表示以玩家所在坐标的相对位置为准的生成方式，3表示以起点坐标和终点坐标中心为准的生成方式（默认方式为1）")==3){
							$pos=$this->status[$name]["生成"];
						}elseif($this->setting->get("1表示以起点坐标为准的生成方式，2表示以玩家所在坐标的相对位置为准的生成方式，3表示以起点坐标和终点坐标中心为准的生成方式（默认方式为1）")==2){
							$pos=array($sender->getX(),$sender->getY(),$sender->getZ());
						}
						if(!isset($args[1])){
							if(isset($this->status[$name]["起点"]) && isset($this->status[$name]["终点"])){
								if(!isset($this->clipboard[$name])){
									$sender->sendMessage("执行错误\n你并没有使用[/newData]来创建临时数据。");
								}else{
										$sender->sendMessage($this->CreateNow($this->clipboard[$name], new Position($pos[0] - 0.5, $pos[1], $pos[2] - 0.5, $sender->getLevel())));
								$sender->sendMessage("§a执行成功,已生成你所需要的建筑。\n§7如果并没有显示出来，原因1：可能说明方块太多导致的内存问题，你可以尝试重新启动服务器或者/reload服务器。\n§7原因2：服务器配置过低导致方块刷新缓慢，只需等待一小会就能看到方块");
								}
							}else{
								$sender->sendMessage("你没有设置起点坐标或终点坐标");
							}
						}else{
							$file=$args[1];
							$posi='Buildings/'.$file.'.yml';
							if(!file_exists($this->getDataFolder().$posi)){
								$sender->sendMessage("执行错误\nCraftBuilder/Buildings路径中并不存在{$file}.yml，请输入[/list]检查是否存在此文件，你可以输入[/new]来创建这个新文件。");
							}else{
							
							if($this->setting->get("1表示以起点坐标为准的生成方式，2表示以玩家所在坐标的相对位置为准的生成方式，3表示以起点坐标和终点坐标中心为准的生成方式（默认方式为1）")==1){
								$offset = array(-0.5, 0,-0.5);
							}elseif($this->setting->get("1表示以起点坐标为准的生成方式，2表示以玩家所在坐标的相对位置为准的生成方式，3表示以起点坐标和终点坐标中心为准的生成方式（默认方式为1）")==3){
								$offset = array(-0.5, 0,-0.5);
							}
							$this->hehe=new Config($this->getDataFolder()."Buildings/{$file}.yml", Config::YAML, array());
 							$起点=$this->hehe->get("start&end");
							$sender->sendMessage("验证：".$起点[0][0].$起点[0][1].$起点[0][2].$起点[1][0].$起点[1][1].$起点[1][2]);
							$blocks=$this->hehe->get("blocks");
							$sender->sendMessage("[第一步]正在执行...\n正在将数据进行chr封装。");
							$newblocks=$this->package($sender,$blocks);
							$this->clipboard[$name]=array($offset, $newblocks);
							$sender->sendMessage("[第二步]正在执行...\n正在将数据进行old解析。");
							$this->CreateNow($this->clipboard[$name], new Position($pos[0] - 0.5, $pos[1], $pos[2] - 0.5, $sender->getLevel()));
							$sender->sendMessage("§a执行成功,已生成你所需要的建筑。\n§7如果没有显示出来，原因1：可能说明方块太多导致的内存问题，你可以尝试重新启动服务器或者/reload服务器。\n§7原因2：服务器配置过低导致方块刷新缓慢，只需等待一小会就能看到方块");
							}
						}
					}else{
						$sender->sendMessage("你没有设置生成点坐标,请使用木斧点击要生成的地面");
					}

				break;
				case "clearData":
				if(isset($args[1])){
					if($args[1]=="全部"){
						$this->ScratchFile->setAll(array());
						$this->ScratchFile->save();
						$sender->sendMessage("成功清除了所有玩家的临时数据");
					}else{
						$theplayer=$args[1];
						$ScratchFile_=$this->ScratchFile->getAll();
						if(isset($ScratchFile_[$theplayer]) || null !==$ScratchFile_[$theplayer]){
							unset($ScratchFile_[$theplayer]);
							$this->ScratchFile->setAll($ScratchFile_);
							$this->ScratchFile->save();
							$sender->sendMessage("成功删除了{$theplayer}创建的临时数据");
						}
					}
				}else{
					$sender->sendMessage("请输入// 清除临时数据 全部/[玩家名]来执行此操作");
				}
				break;
/* 				case "setting"://[命名文件] [内容]
				if(!isset($args[1])){
					$sender->sendMessage("请输入[/cb meg [文件名] [内容]]设置单个文件的服务器描述或者输入[/cb meg auto [内容]]来设置setting默认设置的服务器描述");
				}
				if(!isset($args[2])){
					$sender->sendMessage("请输入[/cb meg [文件名] [内容]]设置单个文件的服务器描述或者输入[/cb meg auto [内容]]来设置setting默认设置的服务器描述");
				}
				if($args[1]=="auto"){
					$this->setting->set("如果其他玩家加载了你创建的建筑文件，OP玩家和后台会收到此讯息",$args[2]);
					
					$this->setting->save();
					$sender->sendMessage("设置成功，如下：".$args[2]);
				}else{
					$file='Buildings/'.$args[1].'.yml';
					if(!file_exists($this->getDataFolder().$file)){
						$sender->sendMessage("{$args[1]}.yml文件不存在，请输入/cb list查询是否存在此文件");
					}
					$this->megg=new Config($this->getDataFolder()."Buildings/{$args[1]}.yml", Config::YAML, array());
					$tihs->megg->set("servermeg",$args[2]);
					$this->megg->save();
					$sender->sendMessage("设置成功,你可以在setting文件中查看，如下：".$args[2]);
				}
				break;
 */				case "check":
				if(!isset($args[1])){
					$sender->sendMessage("请输入[/cb check [文件名]]查看此文件的所有信息");
				}
				$file='Buildings/'.$args[1].'.yml';
				if(!file_exists($this->getDataFolder().$file)){
					$sender->sendMessage("{$$args[1]}.yml文件不存在，请输入/cb list查询是否存在此文件");
				}
				$this->checkk=new Config($this->getDataFolder()."Buildings/{$args[1]}.yml", Config::YAML, array());
				$version=$this->checkk->get("version");
				$fromserver=$this->checkk->get("fromserver");
				$builder=$this->checkk->get("builder");
				$from=$this->checkk->get("from");
				$servermeg=$this->checkk->get("servermeg");
				$describe=$this->checkk->get("describe");
				$connect=$this->checkk->get("connect");
				$sender->sendMessage("-----------------------------------");
				$sender->sendMessage("路径：plugins/CraftBuilder/".$file);
				$sender->sendMessage("服务器全称：".$from);
				$sender->sendMessage("版本号：".$version);#每update一次，版本号将＋1
				$sender->sendMessage("服务器地址：".$fromserver["IP"].":".$fromserver["Port"]);
				$sender->sendMessage("建筑师：".$builder);
				$sender->sendMessage("服务器的描述：".$servermeg);
				$sender->sendMessage("建筑的描述：".$describe);
				$sender->sendMessage("关于服务器：".$connect);
				$sender->sendMessage("-----------------------------------");
				break;
				case "edit":
				if(!isset($args[1]) && !isset($args[2]) && !isset($args[3])){
					$sender->sendMessage("请输入[/cb edit [文件名] 版本/服务器描述/建筑师/服务器全称/IP/port/建筑描述/联系方式 [内容]]编辑文件的信息");
				}
				$file='Buildings/'.$args[1].'.yml';
				if(!file_exists($this->getDataFolder().$file)){
					$sender->sendMessage("{$args[1]}.yml文件不存在，请输入/cb list查询是否存在此文件");
				}
				$this->checkk=new Config($this->getDataFolder()."Buildings/{$args[1]}.yml", Config::YAML, array());
				$version=$this->checkk->get("version");
				$fromserver=$this->checkk->get("fromserver");
				$builder=$this->checkk->get("builder");
				$from=$this->checkk->get("from");
				$servermeg=$this->checkk->get("servermeg");
				$describe=$this->checkk->get("describe");
				$connect=$this->checkk->get("connect");
				switch($args[2]){
					case "版本":
					$haha="version";
					$ha=$version;
					$this->checkk->set("version",$args[3]);
					break;
					case "服务器描述":
					$haha="servermeg";$ha=$servermeg;
					$this->checkk->set("servermeg",$args[3]);
					break;
					case "建筑师":
					$haha="builder";$ha=$builder;
					$this->checkk->set("builder",$args[3]);
					break;
					case "服务器全称":
					$haha="from";$ha=$from;
					$this->checkk->set("from",$args[3]);
					break;
					case "IP":
					$haha="fromserver";
					$fromserver["IP"]=$args[3];$ha=$fromserver;
					$this->checkk->set("fromserver",$fromserver);
					break;
					case "port":
					$haha="fromserver";
					$fromserver["Port"]=$args[3];$ha=$fromserver;
					$this->checkk->set("fromserver",$fromserver);
					break;
					case "建筑描述":
					$haha="describe";$ha=$describe;
					$this->checkk->set("describe",$args[3]);
					break;
					case "联系方式":
					$haha="connect";$ha=$connect;
					$this->checkk->set("connect",$args[3]);
					break;
				}
				$this->checkk->save();
				$sender->sendMessage("{$args[1]}.yml文件的{$haha}区位的{$args[2]}由{$ha}改为{$args[3]}");
				break;
			}
		}else{//使用帮助
			$sender->sendMessage("欢迎使用CraftBuilder插件，本插件完全开源，欢迎各位拆包的伙伴们正常修改，如果你觉得本原插件有什么需要改进的地方欢迎将修改意见或文件传至本插件的官方网站.(使用木斧和木锄来设置生成坐标和框选坐标)\nQQ交流群：427771268\n以下是本插件的使用功能：\n§f/cb newData §7创建一个你框选的方块临时数据。\n§f/cb clearData §7清除你的方块临时数据。\n§f/cb 1 §7添加起点坐标\n§f/cb 2 §7添加终点坐标\n§f/cb new [文件名] §7把方块临时数据转换成一个yml格式的文件，文件路径：CraftBuilder/Buildings/。\n§f/cb list §7遍历Buildings路径下的所有建筑文件，以列表的形式展现出来。\n§f/cb del [文件名] §7删除Buildings路径下的建筑文件。\n§f/cb clearAll §7删除目录下的所有建筑文件（不安全）。\n§f/cb create 无/[文件名] §7若不加入文件名，则生成临时方块数据的建筑，若加入文件名，则生成建筑文件里的建筑。\n§f/cb check [文件名]§7查看建筑文件的所有信息(版本/服务器描述/建筑描述/建筑师/服务器全称/服务器地址/联系方式)。\n§f/cb edit [文件名] 版本/服务器描述/服务器全称/建筑描述/建筑师/IP/port/联系方式 [内容] §7对建筑文件的信息进行编辑（不推荐，比较麻烦，建议后台直接修改原文件）\n§6§l一方世界 §b一方数据");
		}
		break;
		
	}
	}
	public function package(Player $player,$blocks){
		$newblock=array();
		foreach($blocks as $x => $i){
			foreach($i as $y => $j){
				foreach($j as $z => $block){
					$newblock[$x][$y][$z]=chr($block[0]).chr($block[1]);
				}
			}
		}
		$player->sendMessage("[第一步]执行成功.");
		return $newblock;

	}
	public function CreateFile(Player $player,$filename,$selection){
		$file='Buildings/'.$filename.'.yml';
		if(file_exists($this->getDataFolder().$file)){
			return "创建错误，这个文件已存在";
		}else{
			$this->hehe=new Config($this->getDataFolder()."Buildings/{$filename}.yml", Config::YAML, array(
			"version"=>1,//版本信息
			"fromserver"=>"",//服务器地址
			"builder"=>"",//建筑者
			"from"=>"",//服务器名称
			"servermeg"=>"",//服务器的描述
			"describe"=>"",//描述
			"connect"=>"",//联系方式
			"start&end"=>"",//首尾坐标
			"blocks"=>"",//方块数据
			));
			$this->NewFile($player,$selection,$filename);
			return "§a创建成功，你可以输入[/cb create {$filename}]来创造它,还可以输入[/cb edit]对此文件进行编辑";
		}
		
	}
	public function CreateNow($clipboard, Position $pos){
		if(count($clipboard) !== 2){
			return "错误，请输入/cb newData重新设置它";
		}
		$clipboard[0][0] += $pos->getX()- 0.5;
		$clipboard[0][1] += $pos->getY()+1;
		$clipboard[0][2] += $pos->getZ()- 0.5;
		$offset = array_map("round", $clipboard[0]);
		$count = 0;
		foreach($clipboard[1] as $x => $i){
			foreach($i as $y => $j){
				foreach($j as $z => $block){
						$b = Block::get(ord($block{0}), ord($block{1}));
					//$count += (int) $pos->getLevel()->setBlock(new Vector3($x + $offset[0], $y + $offset[1], $z + $offset[2]), $b, false);
					if(0 == $pos->getLevel()->setBlock(new Vector3($x + $offset[0], $y + $offset[1], $z + $offset[2]), $b, false))
					{
						$count++;
					}
					unset($b);
				}
			}
		}
		return "总共有{$count}个方块被转换";
	}
	public function MakeNew(Player $player ,array $position1 ,array $position2){//创建临时文件
		$name=$player->getName();
		if($position1[3]==$position2[3]){
		$selection=array("起点"=>[$position1[0],$position1[1],$position1[2],$position1[3]],
		"终点"=>[$position2[0],$position2[1],$position2[2],$position2[3]]
		);
		$this->ScratchFile->set($name,$selection);
		$this->ScratchFile->save();
		$player->sendMessage("§a[第一步]执行成功\n§7新的临时数据已保存在了ScratchFile.yml文件中,数据如下:\n§7所属玩家:{$name},起点坐标(x=>{$selection["起点"][0]},y=>{$selection["起点"][1]},z=>{$selection["起点"][2]},level=>{$selection["起点"][3]}),终点坐标(x=>{$selection["终点"][0]},y=>{$selection["终点"][1]},z=>{$selection["终点"][2]},level=>{$selection["终点"][3]})");
		/*********************************/
		
		
		}else{
			$player->sendMessage("§c错误\n你的起点坐标{$position1[3]}和终点坐标{$position2[3]}不在一个世界中");
		}
	}
	public function Selection(Player $player ,$selection){
		$level = $this->getServer()->getLevelByName($selection["起点"][3]);
		
		$blocks = array();
		$startX = min($selection["起点"][0], $selection["终点"][0]);
		$endX = max($selection["起点"][0], $selection["终点"][0]);
		$startY = min($selection["起点"][1], $selection["终点"][1]);
		$endY = max($selection["起点"][1], $selection["终点"][1]);
		$startZ = min($selection["起点"][2], $selection["终点"][2]);
		$endZ = max($selection["起点"][2], $selection["终点"][2]);
		$count = $this->countBlocks($selection);
		$player->sendMessage("详细信息：起点{$startX}{$startY}{$startZ}终点{$endX}{$endY}{$endZ}");
		$player->sendMessage("[第二步]执行成功\n§7总共有 $count 个方块被成功被选取");
		for($x = $startX; $x <= $endX; ++$x){
			$blocks[$x - $startX] = array();
			for($y = $startY; $y <= $endY; ++$y){
				$blocks[$x - $startX][$y - $startY] = array();
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $level->getBlock(new Vector3($x, $y, $z));
					$blocks[$x - $startX][$y - $startY][$z - $startZ] = chr($b->getID()).chr($b->getDamage());
					unset($b);
				}
			}
		}
		$player->sendMessage("[第三步]执行成功\n§7已将起点坐标和终点坐标的数据进行chr转换存储到了临时变量[数组]中了");
		return $blocks;
	}
	public function Selection2(Player $player ,$selection){
		$level = $this->getServer()->getLevelByName($selection["起点"][3]);
		
		$blocks = array();
		$startX = min($selection["起点"][0], $selection["终点"][0]);
		$endX = max($selection["起点"][0], $selection["终点"][0]);
		$startY = min($selection["起点"][1], $selection["终点"][1]);
		$endY = max($selection["起点"][1], $selection["终点"][1]);
		$startZ = min($selection["起点"][2], $selection["终点"][2]);
		$endZ = max($selection["起点"][2], $selection["终点"][2]);
		$count = $this->countBlocks($selection);
		$player->sendMessage("详细信息：起点{$startX}{$startY}{$startZ}终点{$endX}{$endY}{$endZ}");
		$player->sendMessage("[第二步]执行成功\n§7总共有 $count 个方块被成功被选取");
		for($x = $startX; $x <= $endX; ++$x){
			$blocks[$x - $startX] = array();
			for($y = $startY; $y <= $endY; ++$y){
				$blocks[$x - $startX][$y - $startY] = array();
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $level->getBlock(new Vector3($x, $y, $z));
					$blocks[$x - $startX][$y - $startY][$z - $startZ] = [$b->getID(),$b->getDamage()];
					unset($b);
				}
			}
		}
		$player->sendMessage("[第三步]执行成功\n§7已将起点坐标和终点坐标的数据进行chr转换存储到了临时变量[数组]中了");
		return $blocks;
	}
	public function NewFile(Player $player,$selection,$file){
		$level = $this->getServer()->getLevelByName($selection["起点"][3]);
		
		$blocks = array();
		$startX = min($selection["起点"][0], $selection["终点"][0]);
		$endX = max($selection["起点"][0], $selection["终点"][0]);
		$startY = min($selection["起点"][1], $selection["终点"][1]);
		$endY = max($selection["起点"][1], $selection["终点"][1]);
		$startZ = min($selection["起点"][2], $selection["终点"][2]);
		$endZ = max($selection["起点"][2], $selection["终点"][2]);
		$count = $this->countBlocks($selection);
		for($x = $startX; $x <= $endX; ++$x){
			$blocks[$x - $startX] = array();
			for($y = $startY; $y <= $endY; ++$y){
				$blocks[$x - $startX][$y - $startY] = array();
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $level->getBlock(new Vector3($x, $y, $z));
					$blocks[$x - $startX][$y - $startY][$z - $startZ] = [$b->getID(),$b->getDamage()];
					unset($b);
				}
			}
		}
		$this->haha2=new Config($this->getDataFolder()."Buildings/{$file}.yml", Config::YAML);
		$this->haha2->set("blocks",$blocks);
		$this->haha2->save();
		$player->sendMessage("[第一步]执行成功§7已将方块数据存储在了{$file}.yml文件中的bolcks区位了\n");
		$lol=array();
		$lol[0]=array($selection["起点"][0],$selection["起点"][1],$selection["起点"][2],$selection["起点"][3]);
		$lol[1]=array($selection["终点"][0],$selection["终点"][1],$selection["终点"][2],$selection["终点"][3]);
		$this->haha2->set("start&end",$lol);
		$this->haha2->save();
		$player->sendMessage("[第二步]执行成功§7已将起始坐标存储进了{$file}.yml文件中的start&end区位了\n");
		$ipport=array("IP"=>$this->getServer()->getIp(),"Port"=>$this->getServer()->getPort());
		$this->haha2->set("fromserver",$ipport);
		$this->haha2->save();
		$player->sendMessage("[第三步]执行成功§7已将服务器的IP地址和端口存放进fromserver区位中了\n");
		$this->haha2->set("servermeg",$this->setting->get("如果其他玩家加载了你创建的建筑文件，OP玩家和后台会收到此讯息"));
		$this->haha2->set("connect",$this->setting->get("设置本服创建的建筑文件默认的联系方式"));
		$emmm=$this->setting->get("如果其他玩家加载了你创建的建筑文件，OP玩家和后台会收到此讯息");
		$em=$this->setting->get("设置本服创建的建筑文件默认的联系方式");
		$emm=$this->setting->get("服务器名称（请认真填写）");
		$this->haha2->set("from",$emm);
		$this->haha2->save();
		$player->sendMessage("[第四步]执行成功§7已将本服创建的描述信息存储进了servermeg区位中了\n如下：服务器名称：{$emm}服务器描述{$emmm}\n还有本服创建的联系方式存储进了connect区位中了\n如下：{$em}");
		
		
	}
	public function onClick(PlayerInteractEvent $event){//起点坐标&生成坐标
		$player=$event->getPlayer();
		$name=$player->getName();
		$item=$event->getItem();
		$block=$event->getBlock();
		$x=$block->getX();
		$y=$block->getY();
		$z=$block->getZ();
		$level=$block->getLevel()->getFolderName();
		if($item->getId()==290){//木锄
			if($player->isOp()){
				if( !isset($this->status[$name]["stat"]) ||  $this->status[$name]["stat"]==1){
					$this->status[$name]["起点"]=[round($block->getX()),round($block->getY()),round($block->getZ()),$block->getLevel()->getFolderName()];
					$player->sendMessage("起点坐标设置成功 §7再点击一次设置终点坐标,用木斧点击方块设置生成地点\n§7(x=>{$x},y=>{$y},z=>{$z},level=>{$level})");
					$this->status[$name]["stat"]=2;
					$event->setCancelled(true);
				}else{
					$this->status[$name]["终点"]=[round($block->getX()),round($block->getY()),round($block->getZ()),$block->getLevel()->getFolderName()];
					$player->sendMessage("终点坐标设置成功§7再点击一次设置起点坐标,用木斧点击方块设置生成地点\n§7(x=>{$x},y=>{$y},z=>{$z},level=>{$level})");
					$this->status[$name]["stat"]=1;
					$event->setCancelled(true);
				}
			}
		}
		if($item->getId()==271){//木斧
			if($player->isOp()){
				$this->status[$name]["生成"]=[round($block->getX()),round($block->getY()),round($block->getZ()),$block->getLevel()->getFolderName()];
				$player->sendMessage("生成坐标设置成功");
				$event->setCancelled(true);
			}
		}
	}

	public function countBlocks($selection, &$startX = null, &$startY = null, &$startZ = null){//计算双坐标内的方块总数
		if($selection["起点"][3] !== $selection["终点"][3]){
			return false;
		}
		$startX = min($selection["起点"][0], $selection["终点"][0]);
		$endX = max($selection["起点"][0], $selection["终点"][0]);
		$startY = min($selection["起点"][1], $selection["终点"][1]);
		$endY = max($selection["起点"][1], $selection["终点"][1]);
		$startZ = min($selection["起点"][2], $selection["终点"][2]);
		$endZ = max($selection["起点"][2], $selection["终点"][2]);
		return ($endX - $startX + 1) * ($endY - $startY + 1) * ($endZ - $startZ + 1);
	}
	
}