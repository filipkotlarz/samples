<?php
namespace Panel\Listing;

use Core\Listing;
use Core\Listing\DataColumn;
use Core\Listing\Column;
use Core\Listing\Row;
use Doctrine\ORM\QueryBuilder;
use Core\EntityRepository\User as UserRepository;
use Core\Entity\User;

/**
 * User form
 *
 * @author Filip Kotlarz
 */
class Users extends Listing\AbstractListing
{
    protected function init()
    {
        $this->setDefaultSorting( 'name' );
        $translator = $this->getTranslator();

        $column = new DataColumn( 'id', 'User.id' );
        $this->addColumn( $column );

        $column = new Column( 'name', 'User.name' );
        $column->setLabel( '[User] Name' )
               ->setFilterable()
               ->setHeader()
               ->setWidth(20);
        $this->addColumn( $column );

        $column = new Column( 'email', 'User.email' );
        $column->setLabel( 'E-mail' )
               ->setFilterable()
               ->setWidth(20);
        $this->addColumn( $column );

        $column = new Column( 'role', 'User.role' );
        $column->setSortable( true )
               ->setLabel( 'Role')
               ->setWidth(5)
               ->setFilterable()
               ->setFieldOptions(UserRepository::getRoleNames($translator) );
        $this->addColumn( $column );

        $column = new Column( 'status', 'User.status' );
        $column->setSortable( true )
               ->setLabel( 'Status')
               ->setWidth(5)
               ->setFilterable()
               ->setFieldOptions( UserRepository::getStatusNames($translator) );
        $this->addColumn( $column );

        $column = new Column( 'created', 'User.created' );
        $column->setSortable( true )
                ->setLabel( 'Created')
                ->setFilterable()
                ->setWidth(15);
        $this->addColumn( $column );

        $column = new Column( 'lastLogin', 'User.lastLogin' );
        $column->setSortable( true )
                ->setLabel( 'Last login')
                ->setFilterable()
                ->setWidth(15);
        $this->addColumn( $column );

        $column = new Column( 'lastVisit', 'User.lastVisit' );
        $column->setSortable( true )
                ->setLabel( 'Last visit')
                ->setFilterable()
                ->setWidth(15);
        $this->addColumn( $column );

        $column = new Column( 'actions' );
        $column->setSortable( false )
               ->setFooter()
               ->setWidth(10);
        $this->addColumn( $column );

    }

    protected function prepareQueryBuilder(QueryBuilder $queryBuilder)
    {
        $queryBuilder
             ->select( $this->getDbColumnsMap() )
             ->from( '\Core\Entity\User', 'User' );
    }

    protected function fallbackOrderQueryBuilder (QueryBuilder $queryBuilder)
    {
        $orderDirection = $this->isSortOrderReversed() ? 'DESC' : 'ASC';
        $queryBuilder->addOrderBy('User.id', $orderDirection);
    }

    protected function prepareRow( Row $row )
    {
        $view = $this->getView();
        $editUrl = $view->url( 'panel/:controller-:id',
                array('id' => $row->id, 'controller' => 'user', 'action' => 'edit') );
        $deleteUrl = $view->url( 'panel/:controller-:id',
                array('id' => $row->id, 'controller' => 'user', 'action' => 'delete') );
        $giveAccessUrl = $view->u('sale','activate-access',array('uid' => $row->id));
        $detailsUrl = $view->url('panel/user/:id', array('id' => $row->id));

        $row['name'] = $view->tag('a', $row['name'], array('href'=>$detailsUrl));

        if($row['status'] !== null)
        {
            $status = UserRepository::getStatusName($row['status'], $view->plugin('translate')->getTranslator());
            if( $row['status'] == User::STATUS_ACTIVE )
                $row['status'] = '<span class="label label-sm label-success">'.$status.'</span>';
            elseif( $row['status'] == User::STATUS_NON_ACTIVE )
                $row['status'] = '<span class="label label-sm label-warning">'.$status.'</span>';
            elseif( in_array($row['status'], array( User::STATUS_BLOCKED, User::STATUS_DELETED ) ) )
                $row['status'] = '<span class="label label-sm label-danger">'.$status.'</span>';
            else
                $row['status'] = $status;
        }

        $row['lastLogin'] = $row['lastLogin'] ? $row['lastLogin']->format('Y-m-d H:i:s') : null;
        $row['lastVisit'] = $row['lastVisit'] ? $row['lastVisit']->format('Y-m-d H:i:s') : null;
        $row['created'] = $row['created'] ? $row['created']->format('Y-m-d H:i:s') : null;


        $row['actions'] = $view->button( $editUrl,
                $view->translate('Edit [verb]'),
                'btn btn-xs btn-primary', 'icon-edit' );
    }
}
