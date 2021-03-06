<?php
namespace USQL\Library\Kafka\Kafka\Protocol\Fetch\Helper;
use USQL\Library\Kafka\Kafka\Consumer;

/**
 * Description of Consumer
 *
 * @author daniel
 */
class Consumer extends HelperAbstract
{
    protected $consumer;

    protected $offsetStrategy;


    public function __construct(Consumer $consumer)
    {
        $this->consumer = $consumer;
    }


    public function onPartitionEof($partition)
    {
        $partitionId = $partition->key();
        $topicName = $partition->getTopicName();
        $offset    = $partition->getMessageOffset();
        $this->consumer->setFromOffset(true);
        $this->consumer->setPartition($topicName, $partitionId, ($offset +1));
    }

    public function onStreamEof($streamKey)
    {

    }

    public function onTopicEof($topicName)
    {

    }
}
