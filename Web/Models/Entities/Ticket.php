<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Util\DateTime;
use Nette\Database\Table\ActiveRow;
use openvk\Web\Models\RowModel;
use Chandler\Database\DatabaseConnection;
use openvk\Web\Models\Repositories\Users;
use Chandler\Database\DatabaseConnection as DB;
use Nette\InvalidStateException as ISE;
use Nette\Database\Table\Selection;

class Ticket extends RowModel
{
    
    protected $tableName = "tickets";

    function getId(): int
    {
        return $this->getRecord()->id;
    }
    
    function getStatus(): string
    {
        if ($this->getRecord()->type === 0) 
        {
            return tr("ticket_status_open_s") . ".";
        } elseif ($this->getRecord()->type === 1) {
            return tr("ticket_status_answered_s") . ".";
        } elseif ($this->getRecord()->type === 2) {
            return tr("ticket_status_closed_s") . ".";
        }
    }
    
    function getType(): int
    {
        return $this->getRecord()->type;
    }
    
    function getName(): string
    {
        return ovk_proc_strtr($this->getRecord()->name, 100);
    }
    
    function getContext(): string
    {
        return $this->getRecord()->text;
    }
    
    function getTime(): DateTime
    {
        return new DateTime($this->getRecord()->created);
    }
    
    function isDeleted(): bool
    {
        if ($this->getRecord()->deleted === 0) 
        {
            return false;
        } elseif ($this->getRecord()->deleted === 1) {
            return true;
        }
    }
    
    function authorId(): int
    {
        return $this->getRecord()->user_id;
    }
    
    function getUser(): user
    {
        return (new Users)->get($this->getRecord()->user_id);
    }
}
