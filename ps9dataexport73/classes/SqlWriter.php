<?php
class SqlWriter
{
    private $fh;
    public function __construct($path)
    {
        $this->fh = fopen($path, 'wb');
        if (!$this->fh) throw new Exception('Cannot open file: '.$path);
    }
    public function w($s) { fwrite($this->fh, $s); }
    public function close()
    {
        if ($this->fh) fclose($this->fh);
        $this->fh = null;
    }
}
