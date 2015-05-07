<?php

require_once 'config.php';

if (file_exists($file['template']) & time() - $time['cache'] < filectime($file['template']))// �����������
	die(readfile($file['template']));

ob_start();// �������� �����

if($style) echo '<link href="http://'.$_SERVER['SERVER_NAME'].dirname($_SERVER['PHP_SELF']).'/template/style.css" rel="stylesheet" type="text/css" media="screen" />';

$temp = new MinecraftServer();// �������������� ����������
foreach($server as $e) {// ������������ ������ ������
	$get = $temp->getq($e[0], $time['out']);// �������� ����������
	if($get['error'])// ���� �� ������� �������� ������, ������� ������
		include 'template/offline.php';
	else {// ���� �� ��
		$all['po'] += $get['player_online'];
		$all['pm'] += $get['player_max'];
		include 'template/online.php';
	}
}

// ���������� ������
$file['record'] =  file_get_contents('cache/record.log');// ����� �����������
if($all['po'] >= $file['record']) {// ���� ������ ������ ������
	file_put_contents('cache/record.log', $all['po']);// �������� ����� ������
	$record['all'] = $all['po'];// ��������� ���������� ������
} else// ���� ���
	$record['all'] = $file['record'];// ��������� ������ ������

// ������ �� ����
$file['record_day'] = file_get_contents('cache/record_day.log');// ����� ����������
if($all['po'] > $file['record_day']) {// ���� ������ ������
	file_put_contents('cache/record_day.log', $all['po']);// �������� ����� ������
	$record['day'] = $all['po'];// ��������� ���������� ������
} else// ���� ���
	$record['day'] = $file['record_day'];// ��������� ������ ������
if(time() - $time['record_day'] > filemtime('cache/timefile.log')){// ���� ��������� ���� ������ ������ ��� �����
	file_put_contents('cache/timefile.log', '');// �������������� ��������� ����
	file_put_contents('cache/record_day.log', $all['po']);// � �������������� ���������� ������
	$record['day'] = $all['po'];// ��������� ���������� ������
}

$all['percent'] = @floor(($all['po']/$all['pm'])*100);// % ������ �������
$all['date'] = date_in_text(filemtime('cache/record.log'));// ����� �������� ���� ��������� �����
require_once 'template/all.php';// ������� ����� ������

echo base64_decode(date_in_text(false, true));// ������ �������
file_put_contents($file['template'], ob_get_contents());// ��������� ����� � ����




class MinecraftServer {// Class written by xPaw & modded by book777
	const STATISTIC = 0x00;
	const HANDSHAKE = 0x09;
	private $socket;
	function getp($address, $timeout = 3) {// Ping
		$thetime = microtime(true);
		if(!$in = @fsockopen($address, 25565, $errno, $errstr, $timeout))
			return [
				'address' => $address,
				'error' => '��������'
			];
		if(round((microtime(true)-$thetime)*1000) > $timeout * 1000)
			return [
				'address' => $address,
				'error' => '������� ����'
			];
		@stream_set_timeout($in, $timeout);
		$ping = round((microtime(true)-$thetime)*1000);
		fwrite($in, "\xFE\x01");
		$data = fread($in, 4096);
		$Len = strlen($data);
		if($Len < 4 || $data[0] !== "\xFF")
			return [
				'address' => $address,
				'error' => '����������� ����'
			];
		$data = substr($data, 3);
		$data = iconv('UTF-16BE', 'UTF-8', $data);
		if($data [1] === "\xA7" && $data[2] === "\x31") {
			$data = explode("\x00", $data);
			return  [
				'address' => $address,
				'player_online' => intval($data[4]),
				'motd' => $this->motd($data[3]),
				'type' => $data[0],
				'player_max' => intval($data[5]),
				'percent' => @floor((intval($data[4])/intval($data[5]))*100),
				'version' => $data[2],
				'ping' => $ping
			];
		}
		$data = explode("\xA7", $data);
		return [
			'address' => $address,
			'player_online' => isset($data[1]) ? intval($data[1]) : 0,
			'player_max' => isset($data[2]) ? intval($data[2]) : 0,
			'percent' => @floor((intval($data[1])/intval($data[2]))*100),
			'version' => '< 1.4',
			'ping' => $ping
		];
	}
	function getq($address, $timeout = 3) {// Query
		$thetime = microtime(true);
		$this->socket = @fsockopen('udp://'.$address, 25565, $ErrNo, $ErrStr, $timeout);
		if($this->socket === false)
			return [
				'error' => '��������',
				'address' => $address
			];
		stream_set_timeout($this->socket, $timeout);
		stream_set_blocking($this->socket, true);
		$Challenge = $this->GetChallenge();
		$info = ['ping' => round((microtime(true)-$thetime)*1000)];
		$data = $this->writedata(self :: STATISTIC, $Challenge.Pack('c*', 0x00, 0x00, 0x00, 0x00));
		if(!$data)
			return $this->getp($address, $timeout);// ������� �������� ������ ������� ��������
		fclose($this->socket);
		$Last = '';
		$data = substr($data, 11);
		$data = explode("\x00\x00\x01player_\x00\x00", $data);
		if(count($data) !== 2)
			return [
				'error' => '��������� ���������� ����',
				'address' => $address
			];
		$info['names'] = explode("\x00", substr($data[1], 0, -2));
		$data = explode("\x00", $data[0]);
		$Keys = [
			'numplayers' => 'player_online',
			'maxplayers' => 'player_max',
			'hostname' => 'motd',
			'version' => 'version',
			'gametype' => 'type',
			'game_id' => 'game',
			'plugins' => 'plugins',
			'map' => 'map'
		];
		if($info['plugins']) {
			$data = explode(': ', $info['plugins'], 2);
			$info['core'] = $data[0];
			if(sizeof($data) == 2)
				$info['plugins'] = explode('; ', $data[1]);
		} else {
			$info['core'] = $info['plugins'];
			unset($info['plugins']);
		}
		foreach($data as $Key => $Value) {
			if(~$Key & 1) {
				if(!array_key_exists($Value, $Keys)) {
					$Last = false;
					continue;
				}
				$Last = $Keys[$Value];
				$info[$Last] = '';
			}
			else if($Last != false)
				$info[$Last] = $Value;
		}
		$info += [
			'address' => $address,
			'player_online' => intval($info['player_online']),
			'player_max' => intval($info['player_max']),
			'percent' => @floor(($info['player_online']/$info['player_max'])*100)
		];
		return $info;
	}
	private function writedata($command, $Append = '') {
		$command = Pack('c*', 0xFE, 0xFD, $command, 0x01, 0x02, 0x03, 0x04).$Append;
		$Length = strlen($command);
		if( $Length !== fwrite($this->socket, $command, $Length))
			return [
				'error' => '��������� ������'
			];
		$data = fread($this->socket, 4096);
		if( $data === false )
			return [
				'error' => '�� ������� ��������� �����'
			];
		if(strlen($data) < 5 || $data[0] != $command[2])
			return false;
		return substr($data, 5);
	}
	private function motd($text) {
		$mass = explode('�', $text);
		foreach ($mass as $val)
			$out .= substr($val, 1);
		return $out;
	}
	private function GetChallenge() {
		$data = $this->writedata(self :: HANDSHAKE);
		if($data === false)
			return [
				'error' => 'failed to receive challenge'
			];
		return Pack('N', $data);
	}
}

function date_in_text($data, $up = false) {// ���� ��� �������� + �������
	$iz = array("Jan",		"Feb",		"Mar",		"Apr",		"May",	"Jun",		"Jul",		"Aug",		"Sep",			"Jct",			"Nov",		"Dec");
	$v = array("������",	"������",	"�����",	"������",	"���",	"����",	"����",	"�������",	"��������",	"�������",	"������",	"�������");
	$vblhod = str_replace($iz, $v, date("j M � H:i", $data));if($up) return 'PCEtLSBieSBib29rNzc3IHJ1YnVra2l0Lm9yZy90aHJlYWRzLzY0NzQyIC0tPg==';
	return $vblhod;
}
?>