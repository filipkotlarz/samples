<?php
namespace Panel\Controller;

use Zend\View\Model\ViewModel;
use Core\Mail\Message;
use Panel\Controller\AbstractAuthenticatedActionController;
use Core\Entity\User\CustomerService;
use Core\EntityRepository\User as UserRepository;
use Core\Http\Exception\ClientError;

/**
 * User form
 *
 * @author Filip Kotlarz
 */
class UserController extends AbstractAuthenticatedActionController
{
    /**
     * User listing
     *
     * Route: panel/user
     */
    public function indexAction()
    {
        $listing = $this->listing( 'Panel\Listing\Users');

        return array( 'listing' => $listing );
    }

    /**
     * New User
     *
     * Route: panel/user/new
     */
    public function newAction()
    {
        $loggedUser = $this
            ->getEntityManager()
            ->getRepository('Core\Entity\User')
            ->find( $this->getIdentity()->getId() );

        $form = $this->createForm('\Panel\Form\User\User', array(
            'loggedUserRole' => $loggedUser->getRole(),
            'editedUserRole' => $loggedUser->getRole(),
        ));

        $request = $this->getRequest();
        if ($request->isPost())
        {
            $user = new \Core\Entity\User();
            $form->bind($user);

            $dataForm = array(
                'name' => $request->getPost()->name,
                'email' => $request->getPost()->email,
                'phone' => $request->getPost()-> phone,
                'birthDate' => $request->getPost()-> birthDate,
                'height' => $request->getPost()-> height,
                'tmrModifier' => $request->getPost()-> tmrModifier,
                'goalWeight' => $request->getPost()-> goalWeight,
                'personality' => $request->getPost()-> personality,
                'activityLevel' => $request->getPost()-> activityLevel,
                'isFemale' => $request->getPost()-> isFemale,
                'status' => $request->getPost()-> status,
                'role' => $request->getPost()-> role,
            );

            $form->setData($dataForm);
            if ($form->isValid())
            {
                $entityManager = $this->getEntityManager();

                $user = $form->getObject();

                $password = ($request->getPost()->password!='')
                                ? $request->getPost()->password
                                : $this->getRandomString(8);

                $entityManager->persist($user);
                $entityManager->flush();

                $user->setPassword($password);

                $entityManager->persist($user);
                $entityManager->flush();

                $message = new Message( 'panel/password-send' );
                $message->addTo( $user->getEmail(), $user->getName() );
                $message->setVar( 'password', $password );

                $mailService = $this->getServiceLocator()
                ->get('MailService');

                $mailService->send( $message );

                $this->flashMessenger()->addSuccessMessage( 'User has been created. Check your e-mail' );
                return $this->redirect()->toRoute(
                        'panel/:controller', array(
                            'controller' => 'user'
                        )
                );
            }
        }

        $view = new ViewModel();
        $view->setVariable('form', $form);
        return $view;
    }

    /**
     * Edit User
     *
     * Route: panel/user/:id/edit
     */
    public function editAction()
    {
        $id = $this->params()->fromRoute('id');

        $loggedUser = $this
                    ->getEntityManager()
                    ->getRepository('Core\Entity\User')
                    ->find( $this->getIdentity()->getId() );

        $editedUser = $this
                    ->getEntityManager()
                    ->getRepository('Core\Entity\User')
                    ->find( $id );

        $form = $this->createForm('\Panel\Form\User\User', array(
            'loggedUserRole' => $loggedUser->getRole(),
            'editedUserRole' => $editedUser->getRole(),
        ));

        if($id)
        {
            $form = $form->bindObjectById($id);
        }

        $request = $this->getRequest();
        if ($request->isPost())
        {
            $data = array(
                'name' => $request->getPost()->name,
                'email' => $request->getPost()->email,
                'phone' => $request->getPost()->phone,
                'birthDate' => $request->getPost()->birthDate,
                'height' => $request->getPost()->height,
                'tmrModifier' => $request->getPost()->tmrModifier,
                'goalWeight' => $request->getPost()->goalWeight,
                'personality' => $request->getPost()->personality,
                'activityLevel' => $request->getPost()->activityLevel,
                'isFemale' => $request->getPost()->isFemale,
                'status' => $request->getPost()->status,
                'role' => $request->getPost()->role,
            );

            if($request->getPost()->password!='')
            {
                $data['password'] = $request->getPost()->password;
            }

            $form->setData($data);

            if ($form->isValid())
            {
                $entityManager = $this->getEntityManager();

                $user = $form->getObject();

                if($request->getPost()->password!='')
                {
                    $user->setPassword($request->getPost()->password);
                }

                $userFromEmail = $entityManager
                        ->getRepository('Core\Entity\User')
                        ->findOneBy(array('email' => $user->getEmail()));

                if($userFromEmail==null || $userFromEmail->getId()==$user->getId())
                {
                    $entityManager->persist($user);
                    $entityManager->flush();

                    $this->flashMessenger()->addSuccessMessage(
                        $this->getServiceLocator()->get('translator')
                            ->translate("User has been modified.")
                    );

                    if( $id)
                    {
                        return $this->redirect()->toRoute(
                            'panel/user/:id', array('id' => $id)
                        );
                    }
                    else
                    {
                        return $this->redirect()->toRoute('panel/user');
                    }
                }
                else
                {
                    $form->setData($request->getPost());

                    $this->flashMessenger()->addErrorMessage(
                        $this->getServiceLocator()->get('translator')
                            ->translate("User hasn't been modified. E-mail already exists.")
                    );
                }
            }
        }

        $view = new ViewModel();
        $view->setVariable('user', $form->getObject());
        $view->setVariable('form', $form);

        return $view;
    }

    /**
     * Delete User
     *
     * Route: panel/user/:id/delete
     */
    public function deleteAction()
    {
        $id = $this->params()->fromRoute('id');
        $em = $this->getEntityManager();

        $user = $em->getRepository('Core\Entity\User')->find( $id );
        $em->remove($user);
        $em->flush();

        $this->flashMessenger()->addSuccessMessage( 'User has been deleted.' );

        return $this->redirect()->toRoute(
                    'panel/:controller', array(
                            'controller' => 'user'
                        )
                );
    }

    /**
     * Get user form
     *
     * Route: panel/user/:id or panel/user/new
     *
     * @return \Panel\Form\User\User
     */
    protected function getForm ()
    {
        $id = $this->params()->fromRoute('id');

        $form = $this->createForm('\Panel\Form\User\User');
        if($id)
        {
            $form = $form->bindObjectById($id);
        }

        return $form;
    }

    /**
     * Generate random string
     *
     * @param int $length Random string length
     *
     * @return string
     */

    public function getRandomString($length)
    {
        $str = "";
        $characters = array_merge(range('A','Z'), range('a','z'), range('0','9'));
        $max = count($characters) - 1;
        for ($i = 0; $i < $length; $i++)
        {
            $rand = mt_rand(0, $max);
            $str .= $characters[$rand];
        }
        return $str;
    }

    public function showAction()
    {
        $userId = $this->params()->fromRoute('id');

        $user = $this->getEntityManager()->getRepository('Core\Entity\User')
                    ->find( $userId );

        if( !$user )
            throw new ClientError\NotFound('User not found');

        $dietsListing = $this->listing( 'Panel\Listing\Users\Diets', 'diets_id');
        $dietsListing->setUser( $user );

        $paymentsListing = $this->listing( 'Panel\Listing\Payments', 'payments_id');
        $paymentsListing->setUser( $user );
        $paymentsListing->getColumn('transactionId')->setFilterable(false);
        $paymentsListing->getColumn('shortcode')->setFilterable(false);

        $testResult = $this->getEntityManager()->getRepository('Core\Entity\PsychoTest\Result')
                    ->findOneBy(array('email' => $user->getEmail()));

        $translator = $this->getServiceLocator()->get('translator');

        return array(
            'user'            => $user,
            'userActivityLevel' => UserRepository::getActivityLevel($user->getActivityLevel(), $translator),
            'testResult'      => $testResult,
            'customerServiceSection' => $this->getCustomerServiceSection($user),
            'dietsListing'    => $dietsListing,
            'paymentsListing' => $paymentsListing,
            'articleRepo'     => $this->getEntityManager()
                                      ->getRepository('Core\Entity\Cms\Article'),
        );
    }

    protected function getCustomerServiceSection($user)
    {
        if ($this->getRequest()->isPost())
        {
            $form = $this->createForm('\Panel\Form\User\CustomerService');

            $form->setData($this->params()->fromPost());

            if ($form->isValid())
            {
                $customerService = $user->getCustomerService();

                $parameters = $this->getRequest()->getPost();

                if($customerService==null)
                {
                    $customerService = new CustomerService();
                }

                $customerService->setSituation($parameters->situation);
                $customerService->setNote($parameters->note);
                $customerService->setUser($user);

                $this->getEntityManager()->persist($customerService);
                $this->getEntityManager()->flush();

                $this->flashMessenger()->addSuccessMessage(
                    $this
                        ->getServiceLocator()
                        ->get('translator')
                        ->translate('Changes have been saved.')
                );
            }

            $return = $form;
        }
        else
        {
            $return = $this->getCustomerServiceForm($user);
        }

        return $return;
    }

    protected function getCustomerServiceForm($user=false)
    {
        $user = ($user)?$user:
            $this
                ->getEntityManager()
                ->getRepository('Core\Entity\User')
                ->find($this->params()->fromRoute('id'));

        $form = $this->createForm('\Panel\Form\User\CustomerService');

        if($user!=null)
        {
            $customerService = $user->getCustomerService();

            if($customerService!=null)
            {
                $form = $form->bindObjectById($customerService->getId());
            }
        }

        return $form;
    }

    public function dietMoveAction()
    {
        $userId = $this->params()->fromRoute('id');
        $dietId = $this->params()->fromRoute('dietId');

        /* @var $userDietRepo \Core\EntityRepository\User\Diet */
        $userDietRepo = $this->getEntityManager()
                             ->getRepository('Core\Entity\User\Diet');

        /* @var $userDiet \Core\Entity\User\Diet */
        $userDiet = $userDietRepo->find( $dietId );

        if( !$userDiet )
            throw new ClientError\NotFound('Diet not fount');

        $form = $this->createForm('\Panel\Form\User\Diet\Move',
                                  array('userDiet' => $userDiet));


        if( $this->getRequest()->isPost() )
        {
            $form->setData($this->params()->fromPost());
            if ($form->isValid())
            {
                $newStartDate = new \DateTime($form->getInputFilter()
                                                   ->getValue('start'));

                if( $userDietRepo->moveStartDate($userDiet, $newStartDate) )
                {
                    $this->flashMessenger()
                         ->addSuccessMessage('Diet has been moved');

                    return $this->redirect()
                                ->toRoute('panel/user/:id', array('id' => $userId));
                }
                else
                {
                    $this->flashMessenger()
                         ->addErrorMessage( 'New start date is in range of other earlier diet.' );
                }
            }
        }
        else
        {
            $form->setData( array( 'id'    => $userDiet->getId(),
                                   'start' => $userDiet->getStart() ) );
        }

        return array(
            'form'     => $form,
            'userDiet' => $userDiet,
        );
    }

    public function dietRemoveAction()
    {
        $userId = $this->params()->fromRoute('id');
        $dietId = $this->params()->fromRoute('dietId');

        $userDietRepo = $this->getEntityManager()->getRepository('Core\Entity\User\Diet');

        /* @var $userDiet \Core\Entity\User\Diet */
        $userDiet = $userDietRepo->find($dietId);

        if (!$userDiet)
        {
            throw new ClientError\NotFound('Diet not fount');
        }

        $em = $this->getEntityManager();
        $userDiet->reset( $em );
        $em->flush();

        $this->flashMessenger()->addSuccessMessage( 'Diet has been deleted' );

        return $this->redirect()->toRoute('panel/user/:id', array( 'id' => $userId ) );
    }

    public function dietClearAction()
    {
        $userId = $this->params()->fromRoute('id');
        $dietId = $this->params()->fromRoute('dietId');

        $userDietRepo = $this->getEntityManager()->getRepository('Core\Entity\User\Diet');

        /* @var $userDiet \Core\Entity\User\Diet */
        $userDiet = $userDietRepo->find($dietId);

        if (!$userDiet)
        {
            throw new ClientError\NotFound('Diet not fount');
        }

        $em = $this->getEntityManager();
        $userDiet->clearSets( $em );

        $em->persist( $userDiet );
        $em->flush();

        $userDiet->createPlan();

        $em->persist( $userDiet );
        $em->flush();

        $this->flashMessenger()->addSuccessMessage( 'Diet plan has been replaced by new one' );

        return $this->redirect()->toRoute('panel/user/:id', array( 'id' => $userId ) );
    }

    public function dietUpdateAction()
    {
        $userId   = $this->params()->fromRoute('id');
        $dietId   = $this->params()->fromRoute('dietId');

        $userDietRepo = $this->getEntityManager()->getRepository('Core\Entity\User\Diet');

        /* @var $userDiet \Core\Entity\User\Diet */
        $userDiet = $userDietRepo->find($dietId);

        if (!$userDiet)
        {
            throw new ClientError\NotFound('Diet not fount');
        }

        $em = $this->getEntityManager();

        $form = $this->createForm(
            '\Panel\Form\User\Diet\Update',
            array('userDiet' => $userDiet)
        );

        if( $this->getRequest()->isPost() )
        {
            $form->setData($this->params()->fromPost());
            if ($form->isValid())
            {
                $fromDate = new \DateTime($form->getInputFilter()->getValue('from'));

                if( $fromDate >= $userDiet->getStart() &&
                    $fromDate <= $userDiet->getEnd() )
                {
                    $userDiet->updatePlan($fromDate);

                    $em->persist($userDiet);
                    $em->flush();

                    $this->flashMessenger()
                         ->addSuccessMessage('Diet plan has been updated');

                    return $this->redirect()->toRoute(
                        'panel/user/:id',
                        array('id' => $userId)
                    );
                }
                else
                {
                    $this->flashMessenger()
                         ->addErrorMessage( sprintf(
                             $this->getServiceLocator()->get('translator')
                                  ->translate('Date must be in the range from %s to %s'),
                             $userDiet->getStart()->format('Y-m-d'),
                             $userDiet->getEnd()->format('Y-m-d')
                         ));
                }
            }
        }
        else
        {
            $form->setData( array(
                'id'    => $userDiet->getId(),
                'from' => $userDiet->getStart() )
            );
        }

        return array(
            'form'     => $form,
            'userDiet' => $userDiet,
        );
    }

    public function dietPreviewAction()
    {
        if ( $this->getRequest()->isXmlHttpRequest() )
        {
            $this->layout( 'layout/modal' );
        }

        $dietId = $this->params()->fromRoute('dietId');

        $userDietRepo = $this->getEntityManager()->getRepository('Core\Entity\User\Diet');

        /* @var $userDiet \Core\Entity\User\Diet */
        $userDiet = $userDietRepo->find($dietId);

        if (!$userDiet)
        {
            throw new ClientError\NotFound('Diet not fount');
        }

        return array(
            'diet' => $userDiet
        );
    }

    public function bmrStatsAction()
    {
        $em = $this->getEntityManager();
        $usersRepo = $em->getRepository( '\Core\Entity\User' );

        $usersAll = $usersRepo->findAll();

        $stats = array();
        $users = array();
        $allCnt = 0;

        foreach( $usersAll as $user )
        {
            if( !$user->getTmr() )
                continue;

            $tmr = round(($user->getTmr())/100)*100;

            if( !isset( $stats[$tmr] ) )
            {
                $stats[$tmr] = 0;
                $users[$tmr] = array();
            }

            $users[$tmr][] = $user->getId();

            ++$stats[$tmr];

            ++$allCnt;
        }

        ksort($stats);

        foreach( $stats as $kcal => $cnt )
        {
            $stats[$kcal] = round($cnt/$allCnt*100, 2) . ' %';
        }

        print_r($stats);

        die;
    }
}
