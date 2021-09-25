<?php
namespace Koshkil\Network\Client;

use Koshkil\Network\Exceptions\TNetworkConnectException;
use Koshkil\Network\Exceptions\TNetworkFolderNotFoundException;
use Koshkil\Network\Exceptions\TNetworkLoginFirstException;
use Koshkil\Network\Exceptions\TNetworkLoginIncorrectException;
use Koshkil\Network\Exceptions\TNetworkUploadFailureException;

class Ftp {
    private $host="";
    private $port=21;
    private $user="";
    private $pass="";
    private $baseFolder="";
    private $handler=null;
    private $loggedIn=false;

    public function __construct($host,$user,$pass,$base) {
        list($h,$p)=explode(":",$host);
        $this->host=$h;
        if ($p) $this->port=$p;
        $this->user=$user;
        $this->pass=$pass;
        $this->baseFolder=$base;
    }

    public function close() {
        if (!$this->handler) {
            throw new TNetworkLoginFirstException("Please call connect() first");
        }
        @ftp_close($this->handler);
    }
    public function connect() {
        $this->handler=@ftp_ssl_connect($this->host,$this->port);
        if (!$this->handler) {
            $this->handler=@ftp_connect($this->host,$this->port);
        }
        if (!$this->handler) {
            throw new TNetworkConnectException("Connection to {$this->host}:{$this->port} refused");
        }
    }

    public function login() {
        if (!$this->handler) {
            throw new TNetworkLoginFirstException("Please call connect() first");
        }
        $this->loggedIn=ftp_login($this->handler,$this->user,$this->pass);
        if (!$this->loggedIn) {
            throw new TNetworkLoginIncorrectException("Login Incorrect. Please review credentials. Attempted login with user: {$this->user} / pass: {$this->pass}");
        }
        if (Application::get('FTP_PASSIVE_MODE')===true) {
            if (!ftp_pasv($this->handler,true)) {
                throw new TNetworkConnectException("Can not set PASSIVE mode ON");
            }
        }
    }

    public function mkdir($dir) {
        if (!$this->handler) {
            throw new TNetworkLoginFirstException("Please call connect() first");
        }
        if (!$this->loggedIn) {
            throw new TNetworkLoginIncorrectException("Login Incorrect. Please review credentials");
        }
        if (substr($this->baseFolder,0,1)!="/") $this->baseFolder="/{$this->baseFolder}";
        if (!@ftp_chdir($this->handler,$this->baseFolder)) {
            throw new TNetworkFolderNotFoundException("Base folder {$this->baseFolder} doesn't exist");
        }
        if (substr($dir,0,1)=="/") $dir=substr($dir,1);
        if (substr($dir,-1)=="/") $dir=substr($dir,0,-1);
        $parts = explode('/',$dir);
        foreach($parts as $folder) {
            if(!@ftp_chdir($this->handler, $folder)){
                ftp_mkdir($this->handler, $folder);
                ftp_chdir($this->handler, $folder);
                //ftp_chmod($ftpcon, 0777, $part);
            }
        }
    }
    public function chdir($dir) {
        if (!$this->handler) {
            throw new TNetworkLoginFirstException("Please call connect() first");
        }
        if (!$this->loggedIn) {
            throw new TNetworkLoginIncorrectException("Login Incorrect. Please review credentials");
        }

        if (substr($this->baseFolder,0,1)!="/") $this->baseFolder="/{$this->baseFolder}";
        if (!@ftp_chdir($this->handler,$this->baseFolder)) {
            throw new TNetworkFolderNotFoundException("Base folder {$this->baseFolder} doesn't exist");
        }
        if (substr($dir,0,1)=="/") $dir=substr($dir,1);
        if (substr($dir,-1)=="/") $dir=substr($dir,0,-1);
        if(!@ftp_chdir($this->handler, $dir)){
            throw new TNetworkFolderNotFoundException("Folder {$dir} doesn't exist");
        }
    }

    public function put($local,$remote) {
        if (!$this->handler) {
            throw new TNetworkLoginFirstException("Please call connect() first");
        }
        if (!$this->loggedIn) {
            throw new TNetworkLoginIncorrectException("Login Incorrect. Please review credentials");
        }
        if (count(explode("/",$remote))>1) {
            $this->chdir(dirname($remote));
            $remote=basename($remote);
        }
        if (!ftp_put($this->handler,$remote,$local,FTP_BINARY)) {
            throw new TNetworkUploadFailureException("File upload failed. Try with PASSIVE mode ON");
        }
    }

    public function delete($remoteFile) {
        if (!$this->handler) {
            KoshkilLog::error("Please call connect() first");
            throw new TNetworkLoginFirstException("Please call connect() first");
        }
        if (!$this->loggedIn) {
            KoshkilLog::error("Login Incorrect. Please review credentials");
            throw new TNetworkLoginIncorrectException("Login Incorrect. Please review credentials");
        }
        if (substr($this->baseFolder,0,1)!="/") $this->baseFolder="/{$this->baseFolder}";
        if (!@ftp_chdir($this->handler,$this->baseFolder)) {
            KoshkilLog::error("Base folder {$this->baseFolder} doesn't exist");
            throw new TNetworkFolderNotFoundException("Base folder {$this->baseFolder} doesn't exist");
        }
        if (substr($remoteFile,0,1)=='/') $remoteFile=substr($remoteFile,1);
        if (!@ftp_delete($this->handler,$remoteFile)) {
            KoshkilLog::error("File delete failed. Remote File: {$remoteFile}");
            //throw new TNetworkUploadFailureException("File delete failed. Remote File: {$remoteFile}");
        }
    }
}
