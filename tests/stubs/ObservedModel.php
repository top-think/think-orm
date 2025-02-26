<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;

class ObservedModel extends Model
{
    protected $table = 'test_event_model';
    protected $autoWriteTimestamp = true;
    protected $eventObserver;

    public function __construct(array $data = [])
    {
        global $observer;
        $this->eventObserver = $observer;
        parent::__construct($data);
    }
}