<?php
namespace Bot\Filebase;

class Wrapper implements \ArrayAccess
{
    private $dbFile;
    private $db = [];
    private $cached;

    public function __construct($dbFile = 'defaultdb.json', $cached = true)
    {
        $this->dbFile = $dbFile;
        if (!file_exists($dbFile)) {
            touch($dbFile);
        } else {
            $this->load();
        }

        $this->cached = true;
    }

    public function offsetExists($key)
    {
        return array_key_exists($key, $this->db);
    }

    public function offsetGet($key)
    {
        if ($this->cached && !count($this->db)) {
            $this->load();
        }

        return $this->db[$key];
    }


    public function offsetSet($key, $value)
    {
        $this->db[$key] = $value;
        $this->save();

        return $value;
    }

    public function offsetUnset($key)
    {
        unset($this->db[$key]);
        $this->save();
    }

    public function clear(){
        $this->db = [];
        $this->save();
    }

    protected function save()
    {
        $result = '';
        foreach ($this->db as $dbEntry => $value) {
            $value = json_encode($value, JSON_PRETTY_PRINT);
            $result .= <<<HEREDOC
,
"{$dbEntry}": {$value}
HEREDOC;
        }

        $result = preg_replace('#^,\n#', '', $result);
        $result = "{\n{$result}\n}\n";

        file_put_contents($this->dbFile, $result, LOCK_EX);
    }

    protected function load()
    {
        $load = file_get_contents($this->dbFile);
        if($load) {
            preg_replace(
                [
                    '#^//.+$#',
                    '#^/\*.*?\*/#',
                ],
                [
                    '',
                    '',
                ],
                $load
            );
            $load = json_decode($load, true) ?: [];
            if (json_last_error() !== JSON_ERROR_NONE) {
                \Util::console('DB ' . $this->dbFile . ' load error: ' . json_last_error_msg());
                exit;
            }
        }
        $this->db = $load ?: [];
    }
}
