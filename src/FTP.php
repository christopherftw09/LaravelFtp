<?php

namespace LaravelFTP;

use Exception;

/**
 * Laravel FTP Class
 * Based on CodeIgniter's FTP Class.
 *
 * @license	https://choosealicense.com/licenses/mit/	MIT License
 */
class FTP {
    protected $connection;

    /**
     * Class Constructor
     *
     * @throws	Exception
     * @return	Boolean
     */
    public function __construct($hostname, $username, $password, $port = 21, $passive = true)
    {
        // Connecting to the ftp server.
        $this->connection = @ftp_connect($hostname, $port);
        if(!$this->_is_conn()) throw new Exception('Unable to establish a connection with the FTP server.');

        // Logging into ftp server.
        if(!@ftp_login($this->connection, $username, $password)) throw new Exception('Login failed.');

        // set passive mode if needed
        ftp_pasv($this->connection, $passive);
        return true;
    }

    /**
     * Class Deconstructor
     *
     * @return	Boolean
     */
    public function __destruct()
    {
        if(!$this->_is_conn()) return false;

        return @ftp_close($this->connection);
    }

    /**
     * Connection Checker
     *
     * Validates the ftp connection.
     *
     * @return	Boolean
     */
    protected function _is_conn()
    {
        return is_resource($this->connection);
    }

    /**
     * Change the directory
     *
     * @param	String	$path
     * @return	Boolean
     */
    public function change_dir($path)
    {
        if(!$this->_is_conn()) return false;

        return @ftp_chdir($this->connection, $path);
    }

    /**
     * Create a directory
     *
     * @param	String	$path
     * @param	Integer	$permissions
     * @return	Boolean
     */
    public function mkdir($path, $permissions = NULL)
    {
        if(!$this->_is_conn()) return false;

        if(!@ftp_mkdir($this->connection, $path)) return false;

        if(isset($permissions)) $this->chmod($path, (int)$permissions);

        return true;
    }

    /**
     * Uploads a file to a remote server
     *
     * @param	String	$remote_path
     * @param	String	$local_path
     *
     * @return	Boolean
     */
    public function upload($local_file, $remote_path)
    {
        if(!$this->_is_conn()) return false;

        if(!file_exists($local_file)) return false;

        return @ftp_put($this->connection, $remote_path, $local_file, FTP_BINARY);
    }

    /**
     * Downloads a file from a remote server.
     *
     * @param	String	$remote_path
     * @param	String	$local_path
     *
     * @return	Boolean
     */
    public function download($remote_path, $local_path)
    {
        if(!$this->_is_conn()) return false;

        if($this->size($remote_path) === -1) return false;

        return @ftp_get($this->connection, $local_path, $remote_path, FTP_BINARY, 0);
    }

    /**
     * Renames a file or directory
     *
     * @param	String	$old_name
     * @param	String	$new_name
     *
     * @return	Boolean
     */
    public function rename($old_name, $new_name)
    {
        if(!$this->_is_conn()) return false;

        return @ftp_rename($this->connection, $old_name, $new_name);
    }

    // move($old, $new)

    /**
     * Create a new file
     *
     * @param	String	$file
     * @return	Boolean
     */
    public function create_file($file)
    {
        if(!$this->_is_conn()) return false;

        if($this->size($file) !== -1) return false; // checking to see if the file already exists.

        return @ftp_fput($this->connection, $file, tmpfile(), FTP_ASCII);
    }

    /**
     * Delete a file
     *
     * @param	String	$file
     * @return	Boolean
     */
    public function delete_file($file)
    {
        if(!$this->_is_conn()) return false;

        return @ftp_delete($this->connection, $file);
    }

    /**
     * Delete Directory
     *
     * deletes a folder and then recursively deletes everything (including sub-folders)
     * contained within it.
     *
     * @param	String	$path
     * @return	Boolean
     */
    public function delete_dir($path)
    {
        if(!$this->_is_conn()) return false;

        // Add a trailing slash to the file path if needed
        $path = preg_replace('/(.+?)\/*$/', '\\1/', $path);

        $files = $this->list_files($path);
        if($files->isNotEmpty())
        {
            foreach($files as $file)
            {
                // If we can't delete then it's probably an directory, so we will recursively call delete_dir().
                if(!preg_match('#/\.\.?$#', $file) && !@ftp_delete($this->connection, $file)) $this->delete_dir($file);
            }
        }
        return @ftp_rmdir($this->connection, $path);
    }

    /**
     * Set file permissions
     *
     * @param	String	$path	File path
     * @param	Integer	$perm	Permissions
     * @return	Boolean
     */
    public function chmod($path, $perm)
    {
        if(!$this->_is_conn()) return false;

        return @ftp_chmod($this->connection, $perm, $path);
    }

    /**
     * Listing the files in a specified directory.
     *
     * @param	String	$path
     * @param	Boolean	$detailed
     * @return	\Illuminate\Support\Collection Instance
     */
    public function list_files($path, $detailed = false)
    {
        if(!$this->_is_conn()) return false;

        if($detailed) return collect(ftp_rawlist($this->connection, $path));

        return collect(ftp_nlist($this->connection, $path));
    }

    // public function mirror($locpath, $rempath)

    /**
     * Read a file on the remote server. (Based on the 'get' function provided by Cannonb4ll/LaravelFtp)
     *
     * @param	String	$file
     * @throws	Exception
     * @return	Boolean|String
     */
    public function read_file($file)
    {
        if(!$this->_is_conn()) return false;

        if($this->size($file) > (2*(1024*1024))) throw new Exception("The requested file is too big to read.");  // 2 MB File Limit

        $temp = fopen('php://temp', 'r+');
        if(!@ftp_fget($this->connection, $temp, $file, FTP_ASCII, 0)) return false;

        // Read what we have written and return it.
        rewind($temp);
        return stream_get_contents($temp);
    }

    /**
     * Save to a file on the remote server. (Based on the 'save' function provided by Cannonb4ll/LaravelFtp)
     *
     * @param	String	$file
     * @throws	Exception
     * @return	Boolean
     */
    public function save_file($file, $content)
    {
        if(!$this->_is_conn()) return false;

        $temp = fopen('php://temp', 'r+');
        fwrite($temp, $content);
        rewind($temp);

        return @ftp_fput($this->connection, $file, $temp, FTP_ASCII, 0);
    }

    // protected function _getext($file)
    // protected function _settype($ext)

    public function size($file)
    {
        return @ftp_size($this->connection, $file);
    }

    public function time($file)
    {
        return @ftp_mdtm($this->connection, $file);
    }
}
