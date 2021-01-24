<?php
class csvReader extends \SplFileObject
{
    public function __construct($filename)
    {
        parent::__construct($filename);
        $this->setFlags(SplFileObject::READ_CSV);
    }
}
