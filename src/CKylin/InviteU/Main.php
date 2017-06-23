<?php


namespace CKylin\InviteU;

//COMMON
use pocketmine\command\Command;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\command\CommandSender;

use CsNle\PointCard\Main as PC;

class Main extends PluginBase implements Listener
{
//On Enable + Config Create.

    public function onEnable() {
	    $this->getServer()->getPluginManager()->registerEvents($this, $this);
            $this->path = $this->getDataFolder();
		@mkdir($this->path);@mkdir($this->path);
		$this->cfg = new Config($this->path."options.yml", Config::YAML,array(
			'enable'=>true,
			'MaxInvited'=>10,
			'Inviter_giftcard'=>array(
				'remain'=>false,
				'days'=>false,
				'level'=>false,
				'point'=>false,
				'expire'=>false,
				'money'=>3000,
				'prefix'=>false,
				'cmd'=>false
			),
			'Invitee_giftcard'=>array(
				'remain'=>false,
				'days'=>false,
				'level'=>false,
				'point'=>false,
				'expire'=>false,
				'money'=>2000,
				'prefix'=>false,
				'cmd'=>false
			),
		));
		$this->PC = PC::$PC;
		// $this->pcs = new Config($this->path."cards.yml", Config::YAML,array(
		// 	'inviter'=>array(),
		// 	'invitees'=>array(),
		// ));
		// $this->pcsex = new Config($this->path."cards_example.yml", Config::YAML,array(
		// 	'inviter'=>array(
		// 		'pointcardscodehere0'=>false,
		// 		'pointcardscodehere1'=>false,
		// 		'pointcardscodehere2'=>false,
		// 		'pointcardscodehere3'=>false,
		// 		'pointcardscodehere4'=>false,
		// 	),
		// 	'invitees'=>array(
		// 		'pointcardscodehere5'=>false,
		// 		'pointcardscodehere6'=>false,
		// 		'pointcardscodehere7'=>false,
		// 		'pointcardscodehere8'=>false,
		// 		'pointcardscodehere9'=>false,
		// 	),
		// ));
		$this->invite = new Config($this->path."invites.yml", Config::YAML,array());
		$this->invited = new Config($this->path."inviteds.yml", Config::YAML,array());
		$this->saveall();
		$this->getLogger()->info(TextFormat::GREEN . 'InviteU is now working.');
	}
	
	public function onDisable() {
		$this->saveDefaultConfig();
		$this->getLogger()->info(TextFormat::BLUE . 'Disabled.');
	}
	
	public function onCommand(CommandSender $s, Command $cmd, $label, array $args) {
		if($cmd=='i'){
			$s->sendMessage('======[Invite]======');
			if(empty($args[0])){
				$s->sendMessage('你的邀请码是：'.$this->getInviteCode($s->getName()));
				$s->sendMessage('你已邀请 ['.$this->getInvitedCount($s->getName()).'/'.$this->getcfg('MaxInvited').'] 个玩家！');
				$s->sendMessage('你的可提取卡密数量是：'.$this->getCardsCount($s->getName()));
				$s->sendMessage('邀请列表：');
				$s->sendMessage($this->getInvited($s->getName()));
			}elseif($args[0]=='c'){
				$this->getCards($s);
			}else{
				$invited = $this->getInvitedCount($s->getName());
				$max = $this->getcfg('MaxInvited');
				if($invited>=$max){
					$s->sendMessage('你填写的邀请码已达到最大次数！');
				}else{
					if($this->is_beInvited($s->getName())){
						$s->sendMessage('你已经填写过邀请码了！');
					}else{
						$result = $this->InviteU($args[0],$s);
						if($result==1){
							$s->sendMessage('你已经填写过这个邀请码了！');
						}elseif($result==2){
							$s->sendMessage('不存在的邀请码！');
						}elseif($result==3){
							$s->sendMessage('邀请已经取消。');
						}elseif($result==4){
							$s->sendMessage('不能邀请自己。');
						}else{
							$s->sendMessage('您已成功被邀请！');
							$s->sendMessage('邀请人：'.$result);
							$this->beInvited($s->getName());
						}
					}
				}
			}
			$s->sendMessage('======[Invite]======');
			return true;
		}
		
	}
	//API

	public function getInvited($p){
		if(!$this->invite->exists($p)) return '';
		$info = $this->invite->get($p);
		$players = $info['invited'];
		$res = '';
		foreach($players as $ps){
			$res.= $ps.', ';
		}
		return $res;
	}

	public function getCardsCount($p){
		if(!$this->invite->exists($p)) return '';
		$info = $this->invite->get($p);
		return $info['usedcards'];
	}

	public function getCards($pe){
		$p = $pe->getName();
		if(!$this->invite->exists($p)) return '';
		$info = $this->invite->get($p);
		$cardcount = $info['usedcards'];
		if($cardcount>=1){
			$cdk = $this->genGiftCard(1);
			if($cdk===false){
				$pe->sendMessage('获取卡密出错，本次不消耗卡密次数，请联系管理员以获得解决方案。');
			}else{
				$info['usedcards']--;
				$this->invite->set($p,$info);
				$this->saveall();
				$pe->sendMessage('成功提取卡密：'.$cdk);
				$pe->sendMessage('成功提取卡密：'.$cdk);
				$pe->sendMessage('成功提取卡密：'.$cdk);
				$pe->sendMessage('成功提取卡密：'.$cdk);
				$pe->sendMessage('请谨慎保存(推荐先截屏)，卡密将只显示一次。');
			}
		}else{
			$pe->sendMessage('没有剩余可提取的卡密了哦，快去邀请小伙伴吧！');
		}
	}

	public function getInvitedCount($p){
		if(!$this->invite->exists($p)) return '';
		$info = $this->invite->get($p);
		return count($info['invited']);
	}

	public function InviteU($code,$pe){
		$p = $pe->getName();
		$all = $this->invite->getALL();
		$found = false;
		foreach($all as $pi => $info){
			//$pe->sendMessage($info['code'].'=='.strtoupper($code));
			if($info['code']==strtoupper($code)){
				if(in_array($p,$info['invited'])){
					return 1;
				}
				$found = true;
				if($pi==$p) return 4;
				$cdk = $this->genGiftCard(2);
				if($cdk===false){
					$pe->sendMessage('获取卡密出错，邀请码填写失败');
					return 3;
				}else{
					$pe->sendMessage('成功提取卡密：'.$cdk);
					$pe->sendMessage('成功提取卡密：'.$cdk);
					$pe->sendMessage('请谨慎保存(推荐先截屏)，卡密将只显示一次。');
				}
				array_push($info['invited'],$p);
				$info['usedcards']++;
				$this->invite->set($pi,$info);
				$this->saveall();
				return $pi;
			}
		}
		if($found===false) return 2;
	}

	public function getInviteCode($p){
		if(!$this->invite->exists($p)) {
			$code = $this->generator();
			$cfg = array(
				'code' => $code,
				'usedcards' => 0,
				'invited' => array()
			);
			$this->invite->set($p,$cfg);
			$this->saveall();
			return $code;
		}else{
			return $this->invite->get($p)['code'];
		}
		
	}

	public function msg($msg,$ps){
		// if(!is_array($ps)){
		// 	$ps = [$ps];
		// }
		// foreach($ps as $p){
		// 	$p->sendMessage($msg);
		// }
		return $ps->sendMessage($msg);
	}

	public function msgALL($msg,$console = true) {//broadcast
	if(!isset($msg)) { return false; }
	$allp = $this->getServer()->getOnlinePlayers();
	foreach($allp as $p){
			$p->sendMessage($msg);
		}
	
	if($console){
		$this->getLogger()->info($msg);
	}
}
//I use new function instead of follows function to print cdk info.
	public function generator($prefix = "") {
		$lib = array("0","1","2","3","4","5","6","7","8","9","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
			//$this->getLogger()->info(TextFormat::RED . '1');
		shuffle($lib);
			//$this->getLogger()->info(TextFormat::RED . '2');
		$return = $prefix.$lib[0].$lib[1].$lib[2].$lib[3].$lib[4].$lib[5];
			//$this->getLogger()->info(TextFormat::RED . '3');
		return $return;
	}
	
	public function getcfg($cfg){
		return $this->cfg->get($cfg);
	}
	
	public function getAll(){
		return $this->cfg->getAll();
	}
	
	public function setcfg($cfg,$value){
		return $this->cfg->set($cfg,$value);
		$this->saveall();
	}
	
	public function setAll($cfg){
		return $this->cfg->setAll($cfg);
		$this->saveall();
	}

	public function beInvited($p,$inviteplayer){
		$this->invited->set($p,$inviteplayer);
		$this->saveall();
	}

	public function is_beInvited($p){
		if($this->invited->exists($p)){
			return true;
		}
		return false;
	}

	public function genGiftCard($type){
		$cfg = array();
		if($type==1){
			$cfg = $this->getcfg('Inviter_giftcard');
		}
		if($type==2){
			$cfg = $this->getcfg('Invitee_giftcard');
		}
		return $this->PC->genCDKbyAPI($cfg);
	}

	public function saveall(){
		$this->cfg->save();
		$this->invite->save();
		$this->invited->save();
	}

}