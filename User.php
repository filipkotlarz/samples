<?php
namespace Panel\Form\User;

use Zend\Stdlib\Hydrator\HydratorInterface;
use Zend\Form\Exception\InvalidArgumentException;
use Core\Form;
use Core\Entity;
use Core\EntityRepository\User as UserRepository;

/**
 * User form
 *
 * @author Filip Kotlarz
 */
class User extends Form\Form
{
    private $loggedUserRole;
    private $editedUserRole;

    public function __construct($name = null, $options = array())
    {
        $this->loggedUserRole = $options['loggedUserRole'];
        $this->editedUserRole = $options['editedUserRole'];

        parent::__construct($name, $options);
    }

    public function init ()
    {
        $factory = $this->getFormFactory();
        $translator = $this->getTranslator();

        $element = $factory->create('User.name');
        $element->setLabel('Name');
        $this->add($element);

        $element = $factory->create('User.email');
        $element->setLabel('E-mail');
        $this->add($element);

        $password = new Form\Element\Password('password');
        $password->setLabel('Password');
        $password->setAttribute('required', false);
        $this->add($password);

        $element = $factory->create('User.phone');
        $element->setLabel('Phone');
        $this->add($element);

        $birthday = $factory->create(array(
            'entity' => 'User.birthDate',
            'type'   => 'textDate'
        ));
        $exampleBirthdayDate = (new \DateTime('1981-11-21'))->format( $birthday->getFormat() );
        $birthday = $factory->create('User.birthDate');
        $birthday->setLabel(sprintf(
            $translator->translate('Birth date (e.g. %s)'),
            $exampleBirthdayDate
        ));
        $this->add($birthday);

        $element = $factory->create('User.height');
        $element->setLabel('Height');
        $this->add($element);

        $element = $factory->create('User.tmrModifier');
        $element->setLabel('TMR modifier');
        $element->setAttribute('required', false);
        $this->add($element);

        $element = $factory->create('User.goalWeight');
        $element->setLabel('Goal weight');
        $this->add($element);

        $userRepository = $this
                            ->getObjectManager()
                            ->getRepository('Core\Entity\User');

        $types = $factory->create(array(
            'entity' => 'User.personality',
            'type'   => 'Select'
        ));
        $types->setLabel('Personality');
        $typesValues = array(null=>'') + $userRepository->getPersonalities();
        $types->setValueOptions($typesValues);
        $this->add($types);

        $types = $factory->create(array(
            'entity' => 'User.activityLevel',
            'type'   => 'Select'
        ));
        $types->setLabel('Activity level');
        $typesValues = UserRepository::getActivityLevels($translator);
        $types->setValueOptions($typesValues);
        $this->add($types);

        $types = $factory->create(array(
            'entity' => 'User.isFemale',
            'type'   => 'Select'
        ));
        $types->setLabel('Sex');
        $typesValues = array(
            1  => $translator->translate('Female'),
            0 => $translator->translate('Male'));
        $types->setValueOptions($typesValues);
        $types->setAttribute('aria-required', 'true');
        $this->add($types);

        // Status dropdown
        $this->add(array(
            'type' => 'Select',
            'name' => 'status',
            'options' => array(
                'label' => 'Status',
                'options' => UserRepository::getStatusNames($translator),
            )
        ));

        $acl = $this->getServiceLocator()->getServiceLocator()->get('Acl');

        $isRoleField = $this->isRoleField($this->loggedUserRole, $this->editedUserRole, $acl);

        if($isRoleField==true)
        {
            $roleOptions = UserRepository::getRolesEditableByUser($this->loggedUserRole, $acl, $translator);

            // Role dropdown
            $this->add(array(
                'type' => 'Select',
                'name' => 'role',
                'options' => array(
                    'label' => 'Role',
                    'options' => $roleOptions,
                )
            ));
        }

        $save = $factory->create(array(
            'type' => 'Submit',
            'name' => 'submit',
        ));
        $save->addClass('btn-primary');
        $save->setValue('Save');

        $cancel = $factory->create(array(
            'type' => 'Link',
            'name' => 'cancel',
        ));
        $cancel->setLabel('Cancel');
        $cancel->setUrl('panel/:controller', array('controller' => 'user'));
        $cancel->setIcon('bolt');

        $this->addButtons($save, $cancel);
    }

    private function isRoleField($loggedUserRole, $editedUserRole, $acl)
    {
        $roles = UserRepository::getRoleIdents();

        $issetRoles = (isset($roles[$loggedUserRole]) && isset($roles[$editedUserRole]));
        $equalRoles = ($loggedUserRole == $editedUserRole);

        $return = ($issetRoles && ($acl->inheritsRole($roles[$loggedUserRole], $roles[$editedUserRole])
                    || $equalRoles))?true:false;

        return $return;
    }

    /**
     * Fetch product from database and bind
     *
     * @param int $id User.id
     *
     * @return User $this
     */
    public function bindObjectById ($id)
    {
        $user = $this->getObjectManager()
                        ->getRepository('Core\Entity\User')
                        ->findOneById($id);
        $this->bind($user);
        return $this;
    }

    /**
     * Get the object used by the hydrator
     *
     * If no object has been set, return new User
     *
     * @return mixed
     */
    public function getObject ()
    {
        if (!$this->object)
        {
            $object = new Entity\User();
            $this->setObject($object);
        }
        return $this->object;
    }

    /**
     * Set the object used by the hydrator
     *
     * Set also on tabs.
     *
     * @param  \Core\Entity\User $user
     *
     * @throws \Zend\Form\Exception\InvalidArgumentException
     * @return \Panel\Form\User\User
     */
    public function setObject ($user)
    {
        if ($user && is_object($user) && !$user instanceof Entity\User)
        {
            throw new InvalidArgumentException(sprintf(
                '%s expects an \Core\Entity\User argument; received "%s"',
                __METHOD__,
                is_object($user) ? get_class($user) : gettype($user)
            ));
        }

        parent::setObject($user);

        if ($user->getId())
        {
            $this->get('buttons')->get('cancel')->setUrl(
                'panel/:controller', array(
                 'controller' => 'user'
                )
            );
        }
        else
        {
            $this->get('buttons')->get('cancel')->setUrl(
                'panel/user/new'
            );
        }

        return $this;
    }



    /**
     * Set the hydrator to use when binding an object to the element
     *
     * Also set also on tabs.
     *
     * @param  \Zend\Stdlib\Hydrator\HydratorInterface $hydrator
     * @return \Panel\Form\User\User
     */
    public function setHydrator (HydratorInterface $hydrator)
    {
        parent::setHydrator($hydrator);

        return $this;
    }



    /**
     * Recursively populate values of attached elements and fieldsets
     *
     * Properly populate tabs' values
     *
     * @param  array|Traversable $data
     *
     * @return void
     */
    public function populateValues ($data)
    {
        return parent::populateValues($data);
    }
}
