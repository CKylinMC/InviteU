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
//PointCard2
use CsNle\PointCard\Main as PC;

class Main extends PluginBase implements Listener
{

    public function onEnable() {
		$this->Version = 'v1.0.2';
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->path = $this->getDataFolder();
		@mkdir($this->path);@mkdir($this->path);
		$this->cfg = new Config($this->path."options.yml", Config::YAML,array(
			'enable'=>true,
			'enableCidChecker'=>true,
			'msg'=>'感谢您加入本服务器！',
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
		$this->invite = new Config($this->path."invites.yml", Config::YAML,array());
		$this->invited = new Config($this->path."inviteds.yml", Config::YAML,array());
        if (!$this->getServer()->getPluginManager()->getPlugin('PointCard')) {
			$this->setcfg('enable',false);
			$this->getLogger()->info(TextFormat::RED . '未找到PointCard插件，本插件无法运行！');
		}else{
			$this->getLogger()->info(TextFormat::GREEN . 'InviteU 成功启动！');
			$this->initCidChecker();
		}
		$this->saveall();
	}
	
	public function onDisable() {
		$this->saveDefaultConfig();
		$this->getLogger()->info(TextFormat::BLUE . 'Disabled.');
	}
	
	public function onCommand(CommandSender $s, Command $cmd, $label, array $args) {
		if($cmd=='i'){
			if(!$this->getcfg('enable')){
				$s->sendMessage('未开启邀请功能。');
			}
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
					if($this->is_beInvited($s)){
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
							$s->sendMessage($this->getcfg('msg'));
							$this->beInvited($s->getName(),$result);
							if($this->cidstatus()) $this->logCid($s);
						}
					}
				}
			}
			$s->sendMessage('======[Invite]======');
			return true;
		}
		if($cmd=='iu'&&$s->isOp()){
			$s->sendMessage('=[InviteU 管理]===========');
			$p1 = !empty($args[0]) ? $args[0] : false;
			$p2 = !empty($args[1]) ? $args[1] : false;
			if(!empty($p1)){
				if($p1=='enable'){
					$this->setcfg('enable',true);
					$s->sendMessage('插件已启用');
				}elseif($p1=='disable'){
					$this->setcfg('enable',false);
					$s->sendMessage('插件已禁用');
				}elseif($p1=='enablecid'){
					$this->setcfg('enableCidChecker',true);
					$s->sendMessage('正在启用Cid检查器');
					$this->initCidChecker();
				}elseif($p1=='disablecid'){
					$this->setcfg('enableCidChecker',false);
					$s->sendMessage('正在禁用Cid检查器');
					$this->initCidChecker();
				}elseif($p1=='msg'){
					if(empty($p2)){
						$s->sendMessage('当前邀请附加消息为：'.$this->getcfg('msg'));
					}else{
						$this->setcfg('msg',$p2);
						$s->sendMessage('成功设置邀请附加消息为：'.$this->getcfg('msg'));
					}
				}elseif($p1=='see'){
					if(empty($p2)){
						$s->sendMessage('用法：/iu see <PlayerName>');
					}else{
						$s->sendMessage('查询 - ' . $p2);
						$s->sendMessage('ta的邀请码是：'.$this->getInviteCode($p2));
						$s->sendMessage('ta已邀请 ['.$this->getInvitedCount($p2).'/'.$this->getcfg('MaxInvited').'] 个玩家！');
						$s->sendMessage('ta的可提取卡密数量是：'.$this->getCardsCount($p2));
						$s->sendMessage('ta的邀请列表：');
						$s->sendMessage($this->getInvited($p2));
					}
				}elseif($p1=='info'){
					$s->sendMessage('InviteU '.$this->Version.' by CKylin');
					$s->sendMessage('Source code: https://github.com/Cansll/InviteU');
					$s->sendMessage('邀请码奖励插件');
				}else{
					$s->sendMessage('支持的选项：enable / disable / enablecid / disablecid / msg / see / info');
				}
			}else{
				$s->sendMessage('iu 命令是 InviteU 的管理员命令，用法是');
				$s->sendMessage('/iu <options> <value>');
				$s->sendMessage('支持的选项：enable / disable / enablecid / disablecid / msg / see / info');
			}
			$s->sendMessage('==========================');
			$this->saveall();
			return true;
		}
		
	}

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
	
	public function generator($prefix = "") {
		$lib = array("0","1","2","3","4","5","6","7","8","9","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
		shuffle($lib);
		$return = $prefix.$lib[0].$lib[1].$lib[2].$lib[3].$lib[4].$lib[5];
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

	public function is_beInvited($pe){
		$p = $pe->getName();
		if($this->cidstatus()){
			if($this->isInvitedByCid($pe)){
				return true;
			}
		}
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
		if($this->cidstatus()) $this->cid->save();
		$this->invite->save();
		$this->invited->save();
	}

	public function initCidChecker(){
		$this->cid = new Config($this->path."cids.yml", Config::YAML,array());
		if($this->cidstatus()){
			$this->getLogger()->info(TextFormat::GREEN . '设备检查器已启用，已加载'.count($this->cid->getAll()).'条记录！');
		}else{
			$this->cid = '';
		}
	}

	public function cidstatus(){
		if(!$this->cfg->exists('enableCidChecker')){
			// $this->getLogger()->info(TextFormat::GREEN . 'cid true');
			$this->setcfg('enableCidChecker',true);
			$this->saveall();
		}
		return $this->getcfg('enableCidChecker');
	}

	public function isInvitedByCid(Player $p){
		$pcid = $p->getClientId();
		$pcid = (string) $pcid;
		if($this->cid->exists($pcid)){
			// $this->getLogger()->info(TextFormat::GREEN . 'cid log '.$pcid);
			return true;
		}else{
			return false;
		}
	}

	public function logCid(Player $p){
		$cid = $p->getClientId();
		$cid = (string) $cid;
		// $this->getLogger()->info(TextFormat::GREEN . 'cid log '.$cid);
		$this->cid->set($cid,array('player'=>$p->getName(),'ip'=>$p->getAddress()));
		$this->saveall();
		
	}

	public function unlogCid(Player $p){
		$cid = $p->getClientId();
		$cid = (string) $cid;
		if($this->cid->exists($cid)){
			$this->cid->unset($cid);
			$this->saveall();
		}
	}

	public function getLogByCid($cid){
		if($this->cid->exists($cid)){
			$result = $this->cid->get($cid);
			$result['cid'] = $cid;
			return $result;
		}else{
			return false;
		}
	}

	public function getLogByName($name){
		$all = $this->cid->getAll();
		$result = false;
		foreach($all as $cid => $info){
			if($info['player']===$name){
				$result = $info;
				$result['cid'] = $cid;
				break;
			}
		}
		return $result;
	}

}