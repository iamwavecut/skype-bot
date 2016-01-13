<?php
namespace Bot\Filebase;

class Factory
{
    /**
     * @param $dbFile
     * @return Wrapper
     */
    public static function create($dbFile)
    {
        $dbs = \Util::store('dbs') ?: [];
        if (!in_array($dbFile, $dbs)) {
            $dbs[$dbFile] = $db = new Wrapper($dbFile);
            \Util::store('dbs', $dbs);

            return $db;
        }

        return \Util::store('dbs')[$dbFile];
    }
}
