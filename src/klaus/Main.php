<?php

namespace klaus;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as Tf;

class Main extends PluginBase implements Listener {
	const TAG = Tf::WHITE . Tf::ITALIC . "[ " . Tf::GREEN . "AntiCode" . Tf::WHITE . " ] ";
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args): bool {
		if ($command->getName () === "inspect") {
			if (! isset ( $args [0] )) {
				$sender->sendMessage ( self::TAG . "Usage: /inspect <Plugin name (case sensitive)>" );
				return true;
			}
			$pluginName = $args [0];
			if ($args [0] === "AntiCode")
				return true;
			$pm = $this->getServer ()->getPluginManager ();
			if ($pm->getPlugin ( $args [0] ) === null) {
				$sender->sendMessage ( self::TAG . "$args[0] The plugin does not exist. Please try again." );
				return true;
			}
			$plugin = $pm->getPlugin ( $args [0] );
			$sender->sendMessage ( "Code checks started." );
			$filePath = $this->getPluginFile ( $plugin );
			$result = [ ];
			if ($this->isPharPlugin ( $filePath )) {
				$sender->sendMessage ( "Start a PHAR-type plug-in check." );
				$pharPath = str_replace ( "\\", "/", rtrim ( $filePath, "\\/" ) );
				foreach ( new \RecursiveIteratorIterator ( new \RecursiveDirectoryIterator ( $pharPath ) ) as $fInfo ) {
					$path = $fInfo->getPathname ();
					$pex = explode ( ".", $path );
					$ext = end ( $pex );
					if ($ext !== "php")
						continue;
					$sender->sendMessage ( "Inspecting : $path" );
					$content = file_get_contents ( $path );
					$lines = explode ( "\n", $content );
					foreach ( $lines as $line ) {
						$rs = $this->checkLine ( $line );
						foreach ( $rs as $harm => $bool ) {
							$result [$harm] = true;
						}
					}
				}
			} else {
				$sender->sendMessage ( "Start a source-type plug-in check" );
				$files = $this->filesInDir ( $filePath );
				foreach ( $files as $file ) {
					$sender->sendMessage ( "Inspecting : $file" );
					$path = pathinfo ( $file );
					$ext = strtolower ( $path ['extension'] );
					if ($ext !== "php")
						continue;
					$content = file_get_contents ( $file );
					$lines = explode ( "\n", $content );
					foreach ( $lines as $line ) {
						$rs = $this->checkLine ( $line );
						foreach ( $rs as $harm => $bool ) {
							$result [$harm] = true;
						}
					}
				}
			}
			$sender->sendMessage ( self::TAG . "test results : $args[0]" );
			if (count ( $result ) === 0) {
				$sender->sendMessage ( self::TAG . "No dangerous sources found in the plugin." );
				return true;
			}
			$sender->sendMessage ( self::TAG . count ( $result ) . "Dangerous sources have been found." );
			foreach ( $result as $harm => $bool ) {
				switch ($harm) {
					case "eval(" :
						$sender->sendMessage ( "eval() Method found. You can run arbitrary php code in the plugin. It may be an obfuscated plugin." );
						break;
					case "exec(" :
					case "passthru(" :
					case "system(" :
						$sender->sendMessage ( $harm . ") Method found. You can run external programs or commands from the plug-in." );
						break;
					case "setop(" :
						$sender->sendMessage ( "setOp() Method found. Plugins can access the OP system." );
						break;
					case "rmdir(" :
					case "unlink(" :
						$sender->sendMessage ( $harm . ") Method found. The plugin can delete files or folders on the server." );
				}
			}
			$sender->sendMessage ( self::TAG . $args [0] . " It is recommended to remove the plugin. Obfuscated plugins can be obfuscated and inspected for more detailed results." );
		}
		return true;
	}
	public function checkLine($line) {
		$result = [ ];
		$line = strtolower ( str_replace ( " ", "", $line ) );
		if ($line == "")
			return $result;
		$harms = [ 
				"eval(",
				"exec(",
				"passthru(",
				"system(",
				"setop(",
				"unlink(",
				"rmdir(" 
		];
		foreach ( $harms as $harm ) {
			if (isset ( explode ( $harm, $line ) [1] )) {
				$result [$harm] = true;
			}
		}
		return $result;
	}
	public function isPharPlugin($filePath) {
		return substr ( $filePath, 0, 7 ) === "phar://";
	}
	public function getPluginFile(\pocketmine\plugin\Plugin $plugin) {
		$ref = new \ReflectionClass ( PluginBase::class );
		$prop = $ref->getProperty ( "file" );
		$prop->setAccessible ( true );
		return $prop->getValue ( $plugin );
	}
	private function filesInDir($tdir) {
		if ($dh = opendir ( $tdir )) {
			$files = array ();
			$in_files = array ();
			while ( $a_file = readdir ( $dh ) ) {
				if ($a_file [0] != '.') {
					if (is_dir ( $tdir . "/" . $a_file )) {
						$in_files = $this->filesInDir ( $tdir . "/" . $a_file );
						if (is_array ( $in_files ))
							$files = array_merge ( $files, $in_files );
					} else {
						array_push ( $files, $tdir . "/" . $a_file );
					}
				}
			}
			closedir ( $dh );
			return $files;
		}
		return true;
	}
}
