<?php
require_once '/server/path/to/aws/aws-autoloader.php';
use Aws\Common\Aws;



class S3Upload {
	private $allowOverride = false; // VERY bad idea to enable this if you use CloudFront to publish your S3 files; but fine if you ONLY use S3
	private $client;
	private $bucket;
	private $folder;
	private $cloudfront;

	private $file_keys = array();
	private $dir_keys = array();

	private $configPath = '/aws/server-php/config/'; // relative to DOCUMENT_ROOT

	private function error($msg) {
		header('HTTP/1.1 400 Bad Request');
		header("Content-Type: application/json; charset=utf-8");
		echo '{"error":"'.$msg.'"}';
		die();
	}

	public function __construct($bucket, $credentialsFile, $folder='') {
		$this->configPath = $_SERVER['DOCUMENT_ROOT'] . $this->configPath;

		$configFile = $this->configPath .$credentialsFile;
		if(!file_exists($configFile)) {
			return $this->error("credentials not found");
		}
		
		$this->bucket = $bucket;

		if($folder && substr($folder, -1) != '/') {
			// folder may be an empty string, but if given, it shuold end on a / or we are NOT checking on a folder but instead on a file prefix (which may also include a path). in this case we only look for folders, therefore:
			$folder .= '/';
		} elseif($folder && substr($folder, -2) == '//') {
			// folder may be an empty string, but if given, it shuold end on a / or we are NOT checking on a folder but instead on a file prefix (which may also include a path). in this case we only look for folders, therefore:
			return $this->error("video id missing");
		}
		$this->folder = $folder;
		
		$this->client = Aws::factory( $configFile )->get('S3');

	}
	private function getFilesInFolder($recursive = false) {
		$folder = $this->folder;

		$listConfig = array(
			'Bucket'    => $this->bucket,
			// 'MaxKeys'	=> 1000, // 1000 is the max of this function! else I need to use iterateItem()
			'Prefix'    => $folder // used to filter by sub-folder (or file-prefix), leave empty for root dir
			,'Delimiter' => '' // if given the output will not include files in subfolders
		);
		if(!$recursive) {
			$listConfig['Delimiter'] = '/'; // if given the output will not include files in subfolders
		}
		try {
			$objects = $this->client->listObjects($listConfig);
		} catch (S3Exception $e) {
			echo $e->getMessage() . "\n";
		}

		# list all folders, only available if $recursive===false
		if($objects["CommonPrefixes"]) {
			foreach($objects["CommonPrefixes"] as $dir) {
				$this->dir_keys[] = $dir["Prefix"];
			}
		}
		# list all files in $this->folder
		if($objects["Contents"]) {
			$length = strlen($folder);
			foreach($objects["Contents"] as $file) {
				$key = substr($file["Key"],$length);
				// README empty dirs will show a single Contents entry with the path of $folder as Key, resulting in an empty entry in file_keys if we dont catch it here
				if($key) {
					$this->file_keys[] = $key; # cut off the path and only return file-names
				}
			}
		}
	}
	public function changeContentType($filename, $newMime) {
		if(!$filename) {
			return $this->error('filename missing');
		}
		if(!$newMime) {
			return $this->error('newMime-type missing');
		}

		try {
			if(strpos($filename, "*")!==0) {
				echo 'tried updating '.$this->bucket.'/'.$this->folder.$filename.":\n\n";
				$cct = $this->client->copyObject(array(
					'Bucket' => $this->bucket, // TARGET
					'Key' => $this->folder . $filename, // TARGET
					'CopySource' => $this->bucket.'/'.$this->folder . $filename, // SOURCE
					'ContentType' => $newMime,
					'MetadataDirective' => 'REPLACE'
				));
			} else {
				$this->getFilesInFolder(true, false, true);
				if(strlen($filename) <= 1) {
					return $this->error('filename bad, allowed "*[string]" or "[string]');
				}
				$filename = substr($filename,1); // strip the leading *
				$filter_length = strlen($filename) *-1;
				$filterFiles = function($var) use ($filename, $filter_length) {
					return ( substr($var, $filter_length)==$filename ); 
				};
				$filtered_keys = array_filter($this->file_keys, $filterFiles);
				echo 'searched in '.$this->bucket.'/'.$this->folder.":\n\n";
				echo "filtered_keys:\n";
				print_r($filtered_keys);
				foreach($filtered_keys as $filename) {
					$this->changeContentType($filename, $newMime);
				}
			}
		} catch (S3Exception $e) {
			echo $e->getMessage() . "\n";
		}

	}

}