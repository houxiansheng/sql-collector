<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
// +---------------------------------------------------------------------------
// | SWAN [ $_SWANBR_SLOGAN_$ ]
// +---------------------------------------------------------------------------
// | Copyright $_SWANBR_COPYRIGHT_$
// +---------------------------------------------------------------------------
// | Version  $_SWANBR_VERSION_$
// +---------------------------------------------------------------------------
// | Licensed ( $_SWANBR_LICENSED_URL_$ )
// +---------------------------------------------------------------------------
// | $_SWANBR_WEB_DOMAIN_$
// +---------------------------------------------------------------------------

namespace USQL\Library\Kafka\Kafka\Protocol\Fetch;

use \USQL\Library\Kafka\Kafka\Protocol\Decoder;
use USQL\Library\Kafka\Kafka\Log;
use USQL\Library\Kafka\Kafka\Protocol\Fetch\Helper\Helper;
use USQL\Library\Kafka\Kafka\Exception\OutOfRange;
use USQL\Library\Kafka\Kafka\Exception;

/**
+------------------------------------------------------------------------------
* Kafka protocol since Kafka v0.8
+------------------------------------------------------------------------------
*
* @package
* @version $_SWANBR_VERSION_$
* @copyright Copyleft
* @author $_SWANBR_AUTHOR_$
+------------------------------------------------------------------------------
*/

class MessageSet implements \Iterator
{
    // {{{ members

    /**
     * kafka socket object
     *
     * @var mixed
     * @access private
     */
    private $stream = null;

    /**
     * messageSet size
     *
     * @var float
     * @access private
     */
    private $messageSetSize = 0;

    /**
     * validByteCount
     *
     * @var float
     * @access private
     */
    private $validByteCount = 0;

    /**
     * messageSet offset
     *
     * @var float
     * @access private
     */
    private $offset = 0;

    /**
     * valid
     *
     * @var mixed
     * @access private
     */
    private $valid = false;

    /**
     * partition object
     *
     * @var \Kafka\Protocol\Fetch\Partition
     * @access private
     */
    private $partition = null;

    /**
     * request fetch context
     *
     * @var array
     */
    private $context = array();

    // }}}
    // {{{ functions
    // {{{ public function __construct()

    /**
     * __construct
     *
     * @param \Kafka\Socket $stream
     * @param int $initOffset
     * @access public
     * @return void
     */
    public function __construct(Partition $partition, $context = array())
    {
        $this->stream = $partition->getStream();
        $this->partition = $partition;
        $this->context   = $context;
        $this->messageSetSize = $this->getMessageSetSize();
        Log::log("messageSetSize: {$this->messageSetSize}", LOG_INFO);
    }

    // }}}
    // {{{ public function current()

    /**
     * current
     *
     * @access public
     * @return void
     */
    public function current()
    {
        return $this->current;
    }

    // }}}
    // {{{ public function key()

    /**
     * key
     *
     * @access public
     * @return void
     */
    public function key()
    {
        return $this->validByteCount;
    }

    // }}}
    // {{{ public function rewind()

    /**
     * implements Iterator function
     *
     * @access public
     * @return integer
     */
    public function rewind()
    {
        $this->valid = $this->loadNextMessage();
    }

    // }}}
    // {{{ public function valid()

    /**
     * implements Iterator function
     *
     * @access public
     * @return integer
     */
    public function valid()
    {
        if (!$this->valid) {
            $this->partition->setMessageOffset($this->offset);

            // one partition iterator end
            Helper::onPartitionEof($this->partition);
        }

        return $this->valid;
    }

    // }}}
    // {{{ public function next()

    /**
     * implements Iterator function
     *
     * @access public
     * @return integer
     */
    public function next()
    {
        $this->valid = $this->loadNextMessage();
    }

    // }}}
    // {{{ protected function getMessageSetSize()

    /**
     * get message set size
     *
     * @access protected
     * @return integer
     */
    protected function getMessageSetSize()
    {
        $data = $this->stream->read(4, true);
        $data = Decoder::unpack(Decoder::BIT_B32, $data);
        $size = array_shift($data);
        if ($size <= 0) {
            throw new OutOfRange($size . ' is not a valid message size');
        }
        $this->stream->read(10, true);//读取10个无用字节
        return $size-10;
    }

    // }}}
    // {{{ public function loadNextMessage()

    /**
     * load next message
     *
     * @access public
     * @return void
     */
    public function loadNextMessage($topicName)
    {
        if ($this->validByteCount >= $this->messageSetSize) {
            return false;
        }

        try {
            if ($this->validByteCount + 12 > $this->messageSetSize) {
                // read socket buffer dirty data
                $this->stream->read($this->messageSetSize - $this->validByteCount);
                return false;
            }
//            $offset = $this->stream->read(8, true);
//            $this->offset  = \Kafka\Protocol\Decoder::unpack(Decoder::BIT_B64, $offset);
//            $messageSize = $this->stream->read(4, true);
//            $messageSize = Decoder::unpack(Decoder::BIT_B32, $messageSize);
//            $messageSize = array_shift($messageSize);
            $this->validByteCount += 12;
//            if (($this->validByteCount + $messageSize) > $this->messageSetSize) {
//                // read socket buffer dirty data
//                $this->stream->read($this->messageSetSize - $this->validByteCount);
//                return false;
//            }
            $msg  = $this->stream->read($this->messageSetSize, true);
            return new FetchResponse($msg, $topicName);
        } catch (Exception $e) {
            Log::log("already fetch: {$this->validByteCount}, {$e->getMessage()}", LOG_INFO);
            return false;
        } catch (Exception $e) {
            Log::log("already fetch: {$this->validByteCount}, {$e->getMessage()}", LOG_INFO);
            return false;
        }

        $this->validByteCount += $messageSize;

        return true;
    }

    // }}}
    // {{{ public function messageOffset()

    /**
     * current message offset in producer
     *
     * @return void
     */
    public function messageOffset()
    {
        return $this->offset;
    }

    // }}}
    // }}}
}
