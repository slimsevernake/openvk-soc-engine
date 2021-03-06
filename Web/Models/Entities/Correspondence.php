<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use Chandler\Database\DatabaseConnection;
use Chandler\Signaling\SignalManager;
use Chandler\Security\Authenticator;
use openvk\Web\Events\NewMessageEvent;
use openvk\Web\Models\Entities\Message;
use openvk\Web\Models\Entities\User;
use openvk\Web\Models\RowModel;
use openvk\Web\Models\Repositories\Users;
use Nette\Database\Table\ActiveRow;

/**
 * A repository of messages sent between correspondents.
 * 
 * A pseudo-repository that operates with messages
 * sent between users.
 */
class Correspondence
{
    /**
     * @var RowModel[] Array of correspondents (usually two)
     */
    private $correspondents;
    /**
     * @var \Nette\Database\Table\Selection Messages table
     */
    private $messages;
    
    /**
     * Correspondence constructor.
     * 
     * Requires two users/clubs to construct.
     * 
     * @param $correspondent - first correspondent
     * @param $anotherCorrespondents - another correspondent
     */
    function __construct(RowModel $correspondent, RowModel $anotherCorrespondent)
    {
        $this->correspondents = [$correspondent, $anotherCorrespondent];
        $this->messages       = DatabaseConnection::i()->getContext()->table("messages");
    }
    
    /**
     * Get /im?sel url.
     * 
     * @returns string - URL
     */
    function getURL(): string
    {
        $id = $this->correspondents[1]->getId();
        $id = get_class($this->correspondents[1]) === 'openvk\Web\Models\Entities\Club' ? $id * -1 : $id;
        
        return "/im?sel=$id";
    }
    
    /**
     * Get correspondents as array.
     * 
     * @returns RowModel[] Array of correspondents (usually two)
     */
    function getCorrespondents(): array
    {
        return $this->correspondents;
    }
    
    /**
     * Fetch messages.
     * 
     * Fetch messages on per page basis.
     * 
     * @param $page - page (defaults to first)
     * @param $perPage - messages per page (defaults to default per page count)
     * @returns \Traversable - iterable messages cursor
     */
    function getMessages(?float $offset = NULL, ?int $perPage = NULL): array
    {
        $query  = file_get_contents(__DIR__ . "/../sql/get-messages.tsql");
        $params = [
            [get_class($this->correspondents[0]), get_class($this->correspondents[1])],
            [$this->correspondents[0]->getId(), $this->correspondents[1]->getId()],
            [$perPage ?? OPENVK_DEFAULT_PER_PAGE]
        ];
        $params = array_merge($params[0], $params[1], array_reverse($params[0]), array_reverse($params[1]), $params[2]);
        
        if(is_null($offset))
            $query = str_replace("\n  AND (`id` < ?)", "", $query);
        else
            array_unshift($params, $offset);
        
        $msgs   = DatabaseConnection::i()->getConnection()->query($query, ...$params);
        $msgs   = array_map(function($message) {
            $message = new ActiveRow((array) $message, $this->messages); #Directly creating ActiveRow is faster than making query
            
            return new Message($message);
        }, iterator_to_array($msgs));
        
        return $msgs;
    }
    
    /**
     * Get last message from correspondence.
     * 
     * @returns Message|null - message, if any
     */
    function getPreviewMessage(): ?Message
    {
        $messages = $this->getMessages(null, 1);
        return $messages[0] ?? NULL;
    }
    
    /**
     * Send message as user, who is currently logged in.
     * 
     * @deprecated
     * @returns Message|false - resulting message, or false in case of non-successful transaction
     */
    function sendMessage(Message $message, bool $dontReverse = false)
    {
        $user = (new Users)->getByChandlerUser(Authenticator::i()->getUser());
        if(!$user) return false;
        
        $ids     = [$this->correspondents[0]->getId(), $this->correspondents[1]->getId()];
        $classes = [get_class($this->correspondents[0]), get_class($this->correspondents[1])];
        if(!in_array($user->getId(), $ids)) return false;
        if($ids[1] === $user->getId() && !$dontReverse) {
            $ids     = array_reverse($ids);
            $classes = array_reverse($classes);
        }
        
        $message->setSender_Id($ids[0]);
        $message->setRecipient_Id($ids[1]);
        $message->setSender_Type($classes[0]);
        $message->setRecipient_Type($classes[1]);
        $message->setCreated(time());
        $message->save();
        
        # да
        if($ids[0] !== $ids[1]) {
            $event = new NewMessageEvent($message);
            (SignalManager::i())->triggerEvent($event, $ids[1]);
        }
        
        return $message;
    }
}
