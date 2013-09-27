<?php

/* todo

1) V případě smazání subdir/file a subdir je prázdná, tak ji to nesmaže
2) Přesunutí celého archivu

*/


if (!empty($_REQUEST['payload'])){

$config["username"] = "git-username";
$config["password"] = "git-password";
$config["repository"] = "git-repository";

$config["branch"]["master"] = "remote"; /* local or remote*/
$config["branch"]["staging"] = "local"; /* local or remote*/

$config["repositoryRoot"]["staging"] = "staging"; /* needed also when config[branch][staging] is remote because cache */
$config["repositoryRoot"]["master"] = "master"; /* needed also when config[branch][master] is remote because cache */

$config["master"]["server"] = "master-server"; /* only needed when config[branch][master] is "remote" */
$config["master"]["username"] = "master-username"; /* only needed when config[branch][master] is "remote" */
$config["master"]["password"] = "master-pwd"; /* only needed when config[branch][master] is "remote" */
$config["master"]["root"] = "master-gir-root"; /* only needed when config[branch][master] is "remote" */




$logFile = 'log/log'.date('m-d-y-H-i-s').'.txt';

function mkpath($path)
{
   if(@mkdir($path) or file_exists($path)) return true;
   return (mkpath(dirname($path)) and mkdir($path));
}

function getBranchName($branchRef){
	$getBranch = explode("/",$branchRef);
	return $getBranch[count($getBranch)-1];
}

function getFileFromRepo($fileName,$branch){
	global $config;
	$username = $config["username"];
	$password = $config["password"];
	$repository = $config["repository"];
	$rootDir = $config["repositoryRoot"][$branch];
	
	`curl -u '$username:$password' -L  -o $rootDir/$fileName https://raw.github.com/$username/$repository/$branch/$fileName`;
}

function deleteDirectory($dirPath) {
    if (is_dir($dirPath)) {
        $objects = scandir($dirPath);
        foreach ($objects as $object) {
            if ($object != "." && $object !="..") {
                if (filetype($dirPath . DIRECTORY_SEPARATOR . $object) == "dir") {
                    deleteDirectory($dirPath . DIRECTORY_SEPARATOR . $object);
                } else {
                    unlink($dirPath . DIRECTORY_SEPARATOR . $object);
                }
            }
        }
    reset($objects);
    rmdir($dirPath);
    }
}

function uploadFiles($dirPath,$branchDir) {
global $config;
global $connection;

    if (is_dir($dirPath)) {
        $objects = scandir($dirPath);
        foreach ($objects as $object) {
            if ($object != "." && $object !="..") {
                if (filetype($dirPath . DIRECTORY_SEPARATOR . $object) == "dir") {
                    ftp_mkdir($connection, str_replace($branchDir."/","",$dirPath . DIRECTORY_SEPARATOR . $object));
                    uploadFiles($dirPath . DIRECTORY_SEPARATOR . $object,$branchDir);
                } else {
                    ftp_put($connection, str_replace($branchDir."/","",$dirPath . DIRECTORY_SEPARATOR . $object), $dirPath . DIRECTORY_SEPARATOR . $object, FTP_ASCII);
                }
            }
        }
    reset($objects);
    }
}


try
{
  $payload = json_decode($_REQUEST['payload']);
}
catch(Exception $e)
{
  exit(0);
}


$deploy = $payload;

$branch = getBranchName($deploy->ref);

/* logging */

file_put_contents('log.txt', print_r($payload,true));
fopen($logFile, 'w');
file_put_contents($logFile, print_r($payload,true));



if (!empty($deploy->commits)){
	foreach ($deploy->commits as $commit){
		if (!empty($commit->modified)){
			foreach ($commit->modified as $file){  // check modified files
				if (stripos($file,"/")){ // contains directories
					$getPathInfo = pathinfo($file);
					mkpath($config["repositoryRoot"][$branch]."/".$getPathInfo["dirname"]);
				}
				getFileFromRepo($file,$branch);
				}
		}
	}



	foreach ($deploy->commits as $commit){
		if (!empty($commit->added)){
			foreach ($commit->added as $file){  // check new files
				if (stripos($file,"/")){ // contains directories
					$getPathInfo = pathinfo($file);
					mkpath($config["repositoryRoot"][$branch]."/".$getPathInfo["dirname"]);
				}
				getFileFromRepo($file,$branch);
				}
		}
	}
	
	
	foreach ($deploy->commits as $commit){
		if (!empty($commit->removed)){
			foreach ($commit->removed as $file){  // check removed files
				unlink($config["repositoryRoot"][$branch]."/".$file);
				}
		}
	}
	
	
	/* remote */
	if ($config["branch"][$branch] == "remote"){
	

		$connection = ftp_connect($config[$branch]["server"]);
		$login_result = ftp_login($connection, $config[$branch]["username"], $config[$branch]["password"]);
		ftp_chdir($connection, $config[$branch]["root"]); 
		uploadFiles($branch,$branch);
		ftp_close($connection);

		deleteDirectory($config["repositoryRoot"][$branch]);
		mkdir($config["repositoryRoot"][$branch]);

		}
	
}

}
else{
	echo "Forbidden";
}
?>

