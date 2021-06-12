<?php

namespace AppBundle\Controller;

use AppBundle\Component\Validation\ValidateEntity;
use AppBundle\Model\Vendor\ReverseRebate;
use AppBundle\Model\Vendor\ReverseRebateHistoryFacade;
use AppBundle\Model\Vendor\Vendor;
use DateTime;
use JavierEguiluz\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class ReverseRebateController extends AdminController
{
    /**
     * Pokud je v GET parametr 'vendor', přednastaví ReverseRebate na tohoto vendora.
     * 'vendor' parametr se používá při volání 'newModal' action.
     *
     * @throws \LogicException
     *
     * @return ReverseRebate
     */
    protected function createNewReverseRebateEntity()
    {
        $entityFullyQualifiedClassName = $this->entity['class'];
        /** @var ReverseRebate $entity */
        $entity = new $entityFullyQualifiedClassName();
        $vendorId = $this->request->query->get('vendor');

        if ($vendorId !== null && is_numeric($vendorId)) {
            $vendor = $this->getDoctrine()
                ->getRepository(Vendor::class)
                ->find($vendorId);
            $entity->setVendor($vendor);
        }

        return $entity;
    }

    /**
     * Musel jsem ji celou kopírovat kvůli podmínečnému volání persist().
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \LogicException
     *
     * @return RedirectResponse|Response
     */
    protected function newReverseRebateAction()
    {
        $this->dispatch(EasyAdminEvents::PRE_NEW);

        $entity = $this->createNewReverseRebateEntity();

        $easyadmin = $this->request->attributes->get('easyadmin');
        $easyadmin['item'] = $entity;
        $this->request->attributes->set('easyadmin', $easyadmin);

        $fields = $this->entity['new']['fields'];

        $newForm = $this->createNewForm($entity, $fields);

        $newForm->handleRequest($this->request);
        if ($newForm->isSubmitted() && $newForm->isValid()) {
            $this->dispatch(EasyAdminEvents::PRE_PERSIST, ['entity' => $entity]);
            // případ kdy existuje stejný vendor/rabat mladší 24 hod, dojde k update (namísto insert)
            if ($this->prePersistReverseRebateEntity($entity)) {
                $this->em->persist($entity);
            }
            $this->em->flush();

            $this->dispatch(EasyAdminEvents::POST_PERSIST, ['entity' => $entity]);

            $refererUrl = $this->request->query->get('referer', '');

            return !empty($refererUrl)
                ? $this->redirect(urldecode($refererUrl))
                : $this->redirect($this->generateUrl('easyadmin', ['action' => 'list', 'entity' => $this->entity['name']]));
        }

        $this->dispatch(EasyAdminEvents::POST_NEW, [
            'entity_fields' => $fields,
            'form' => $newForm,
            'entity' => $entity,
        ]);

        return $this->render($this->entity['templates']['new'], [
            'form' => $newForm->createView(),
            'entity_fields' => $fields,
            'entity' => $entity,
        ]);
    }

    /**
     * @param ReverseRebate|array $data
     */
    private function saveReverseRebateToHistory($data)
    {
        $historyFacade = new ReverseRebateHistoryFacade();
        $validateEntity = new ValidateEntity($this->container->get('validator'));
        if (is_array($data)) {
            $reverseRebateHistory = $historyFacade->copyHistoryFromArray($data);
        } else {
            $reverseRebateHistory = $historyFacade->copyHistoryFromObject($data);
        }
        $validateEntity->validateEntityAndReportErrors($reverseRebateHistory);
        $this->em->persist($reverseRebateHistory);
    }

    /**
     * Dochází ke kontrole, pokud stejný rabat je mladší jak 24 hodin, tak dojde k jeho update, jinak se vloží nový řádek.
     *
     * @param ReverseRebate $entity
     *
     * @return bool
     */
    protected function prePersistReverseRebateEntity($entity)
    {
        $repository = $this->em->getRepository(ReverseRebate::class);
        $item = $repository->findVendorRebate($entity->getVendor(), $entity->getRebate());

        if (!empty($item)) {
            /** @var ReverseRebate $reverseRebate */
            $reverseRebate = $item[0];
            $created = $reverseRebate->getCreated();
            $datetime = new DateTime('now');
            $datetime->modify('-1 day');
            if ($created > $datetime) {
                $reverseRebate->setTurnoverMin($entity->getTurnoverMin());
                $reverseRebate->setTurnoverMax($entity->getTurnoverMax());

                return false;
            } else {
                $this->em->remove($reverseRebate);
                $this->em->flush();
                $this->saveReverseRebateToHistory($reverseRebate);
            }
        }

        return true;
    }

    /**
     * Pokud je editovaný ReverseRebate starší než 24 hodin, přesunout jeho původní hodnoty do History.
     *
     * @param ReverseRebate $entity
     */
    protected function preUpdateReverseRebateEntity(ReverseRebate $entity)
    {
        $originalArray = $this->em->getUnitOfWork()->getOriginalEntityData($entity);

        $created = $entity->getCreated();
        $datetime = new DateTime('now');
        $datetime->modify('-1 day');

        // pokud je editovaný prvek starší než 24 hodin, zkopírovat jej do historie
        if ($created < $datetime) {
            $this->saveReverseRebateToHistory($originalArray);
            $entity->setCreated(new DateTime('now'));
        }
    }

    /**
     * @param ReverseRebate $entity
     */
    protected function preRemoveReverseRebateEntity(ReverseRebate $entity)
    {
        $created = $entity->getCreated();
        $datetime = new DateTime('now');
        $datetime->modify('-1 day');

        // pokud je mazaný prvek starší než 24 hodin, zkopírovat jej do historie
        if ($created < $datetime) {
            $this->saveReverseRebateToHistory($entity);
        }
    }

    /**
     * Volá se z editace Dodavatele, popup okno pro přidání ReverseRabat.
     *
     * @return Response
     */
    public function newModalAction()
    {
        $this->entity['templates']['new'] = 'AppBundle::newModal.html.twig';
        $easyadmin = $this->request->attributes->get('easyadmin');
        $easyadmin['view'] = 'new';
        $this->request->attributes->set('easyadmin', $easyadmin);
        $newActionReturn = $this->newReverseRebateAction();

        if ($newActionReturn->getStatusCode() === 200) {
            return $newActionReturn;
        }

        return $this->render('AppBundle::modalPopupClose.html.twig');
    }
}
