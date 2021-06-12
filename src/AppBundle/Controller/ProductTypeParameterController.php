<?php

namespace AppBundle\Controller;

use AppBundle\Model\Product\ProductType\ProductType;
use AppBundle\Model\Product\ProductType\ProductTypeParameter;
use AppBundle\Model\Product\ProductType\ProductTypeParameterFacade;
use AppBundle\Model\Product\ProductType\ProductTypeParameterRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Response;

class ProductTypeParameterController extends AdminController
{
    /**
     * @return Response
     */
    public function newModalAction(): Response
    {
        $session = $this->request->getSession();
        $entity = $this->createNewProductTypeParameterEntity();

        $easyadmin = $this->request->attributes->get('easyadmin');
        $easyadmin['item'] = $entity;
        $easyadmin['view'] = 'new';
        $this->request->attributes->set('easyadmin', $easyadmin);

        $fields = $this->entity['new']['fields'];

        $newForm = $this->createProductTypeParameterNewForm($entity, $fields);

        $newForm->handleRequest($this->request);
        if ($newForm->isSubmitted() && $newForm->isValid()) {
            $copyParameterGroup = $entity->getCopyParameterGroup();

            if ($copyParameterGroup === true) {
                $groupData = $this->getProductTypeParameterRepository()->getLastUsedGroupParameters($entity->getParameterGroup());
                $this->em->persist($entity);
                $parameterEntities = $this->persistGroupData($groupData, $entity);
                $jsonData = $this->getGroupDataForJson($parameterEntities, $entity);
            } else {
                $this->prePersistProductTypeParameterEntity($entity);
                $this->em->persist($entity);
                $jsonData[] = $this->getDataForJson($entity);
            }

            $session->set('parameters_data_json', $jsonData);
            $this->em->flush();
            $this->setWorkingEntity($entity);

            if ($this->request->request->has('save_and_add')) {
                $urlParameters = $this->getSaveAndAddNewModalUrlParameters();

                return $this->redirect($this->generateUrl('easyadmin', $urlParameters));
            }

            $render = $this->render(
                'AppBundle::modalPopupClose.html.twig',
                ['parameters_data_json' => $this->jsonData($jsonData), 'action' => 'new']
            );
            $session->set('parameters_data_json', null);

            return $render;
        }

        $render = $this->render('AppBundle::newModal.html.twig', [
            'form' => $newForm->createView(),
            'entity_fields' => $fields,
            'entity' => $entity,
            'parameters_data_json' => $this->jsonData($session->get('parameters_data_json')),
        ]);
        $session->set('parameters_data_json', null);

        return $render;
    }

    /**
     * Pro vytvoření nové entity ve skupině je potřeba změnit position okolních (odsunout je dolů).
     *
     * @param ProductTypeParameter $entity
     */
    protected function prePersistProductTypeParameterEntity(ProductTypeParameter $entity)
    {
        if ($entity->getParameterGroup() && $entity->getParameter()) {
            $this->getFacade()->moveProductTypeParametersDown($entity->getProductType(), $entity->getPosition());
        }
    }

    /**
     * Rozšiřuji parent EasyAdmin metodu o validation_groups option.
     *
     * @param $entity
     * @param $view
     *
     * @return mixed
     */
    protected function getProductTypeParameterEntityFormOptions($entity, $view)
    {
        $formOptions = $this->entity[$view]['form_options'];
        $formOptions['entity'] = $this->entity['name'];
        $formOptions['view'] = $view;

        $type = $this->request->query->get('type');
        $formOptions['validation_groups'] = [$type];

        return $formOptions;
    }

    /**
     * Přednastaví form na daný productType a setPosition pro nově vytvářený prvek.
     * Pokud je v GET parametr 'productType', přednastaví ProductTypeParameter na tento productType.
     * 'productType' parametr je definován a posílán z ProductTypeParameterType (add_url_group).
     *
     * @throws \LogicException
     *
     * @return ProductTypeParameter
     */
    protected function createNewProductTypeParameterEntity(): ProductTypeParameter
    {
        $entityFullyQualifiedClassName = $this->entity['class'];
        /** @var ProductTypeParameter $entity */
        $entity = new $entityFullyQualifiedClassName();
        $productTypeId = $this->request->query->get('productType');
        $groupId = $this->request->query->get('group');

        if ($productTypeId !== null && is_numeric($productTypeId)) {
            $productType = $this->em->getRepository(ProductType::class)->find($productTypeId);
            $entity->setProductType($productType);
            if ($groupId) {
                // nastavení skupiny a poslední pozice v této skupině
                $parameterGroupEntity = $this->getProductTypeParameterRepository()->find($groupId);
                $groupEntity = $parameterGroupEntity->getParameterGroup();
                $entity->setParameterGroup($groupEntity);
                $lastPosition = $this->getProductTypeParameterRepository()->getLastPositionInGroup($productType, $groupEntity);
            } else {
                $lastPosition = $this->getProductTypeParameterRepository()->getLastPosition($productType);
            }
            $entity->setPosition($lastPosition + 1);
        }

        return $entity;
    }

    /**
     * Používá se pro modální okna, formuláře, kde nepotřebujeme checkbox políčka. Proto jsou z formu odebrána.
     *
     * @param ProductTypeParameter $entity
     * @param array                $entityProperties
     *
     * @return Form
     */
    protected function createProductTypeParameterNewForm(ProductTypeParameter $entity, array $entityProperties): Form
    {
        $form = $this->createEntityForm($entity, $entityProperties, 'new');
        $form->remove('required');
        $form->remove('filter');
        $form->remove('collapsed');
        $form->remove('displayNegativeValue');

        $type = $this->request->query->get('type');
        if ($type === 'group') {
            $form->remove('parameter');
        } elseif ($type === 'parameter') {
            $form->remove('copyParameterGroup');
            $form->remove('parameterGroup');
        } else {
            $form->remove('copyParameterGroup');
        }

        // nastavení informací pro autocomplete query builder
        $this->get('session')->set('autocomplete_productType', $entity->getProductType()->getId());

        return $form;
    }

    /**
     * Aby se jako návratový 'json ajax data' neposílal celý entity object, tak jsou vyselektované konkrétní hodnoty.
     *
     * @param ProductTypeParameter $newEntity
     *
     * @return array
     */
    protected function getDataForJson(ProductTypeParameter $newEntity): array
    {
        $jsonData = [];
        $jsonData['id'] = $newEntity->getId();

        if ($newEntity->getParameterGroup()) {
            if ($newEntity->getParameter()) {
                $jsonData['type'] = 'parameter';
                $jsonData['title'] = $newEntity->getParameter()->getTitle();
                $jsonData['id_parameter'] = $newEntity->getParameter()->getId();
                $jsonData['unit'] = $newEntity->getParameter()->getUnit();
                $jsonData['id_group'] = $this->getProductTypeParameterRepository()->getGroupIdForParameter($newEntity);
            } else {
                $jsonData['type'] = 'group';
                $jsonData['title'] = $newEntity->getParameterGroup()->getTitle();
            }
        } else {
            $jsonData['type'] = 'parameter';
            $jsonData['title'] = $newEntity->getParameter()->getTitle();
            $jsonData['id_parameter'] = $newEntity->getParameter()->getId();
            $jsonData['unit'] = $newEntity->getParameter()->getUnit();
            $jsonData['id_group'] = -1;
        }

        return $jsonData;
    }

    /**
     * Stará se o smazání parametru nebo celé skupiny parametrů.
     *
     * @param ProductTypeParameter $entity
     */
    protected function preRemoveProductTypeParameterEntity(ProductTypeParameter $entity)
    {
        $this->getFacade()->deleteProductTypeParameterEntity($entity);
    }

    /**
     * @return EntityRepository|ProductTypeParameterRepository
     */
    private function getProductTypeParameterRepository(): ProductTypeParameterRepository
    {
        return $this->em->getRepository(ProductTypeParameter::class);
    }

    /**
     * @return ProductTypeParameterFacade
     */
    private function getFacade(): ProductTypeParameterFacade
    {
        return new ProductTypeParameterFacade($this->em);
    }

    /**
     * @return array
     */
    protected function getSaveAndAddNewModalUrlParameters(): array
    {
        $parameters = ['productType', 'type', 'group', 'body'];
        $urlParameters = ['action' => 'newModal', 'entity' => $this->entity['name']];
        foreach ($parameters as $parameter) {
            $urlParameters[$parameter] = $this->request->query->get($parameter);
        }

        return $urlParameters;
    }

    /**
     * @param $groupData
     * @param ProductTypeParameter $entity
     *
     * @return array
     */
    private function persistGroupData($groupData, ProductTypeParameter $entity)
    {
        $position = $entity->getPosition();
        $parameterEntities = [];

        /** @var ProductTypeParameter $groupItem */
        foreach ($groupData as $groupItem) {
            $parameterEntity = new ProductTypeParameter();
            $parameterEntity->setProductType($entity->getProductType());
            $parameterEntity->setParameterGroup($groupItem->getParameterGroup());
            $parameterEntity->setParameter($groupItem->getParameter());
            $parameterEntity->setPosition(++$position);
            $parameterEntity->setRequired($groupItem->getRequired());
            $parameterEntity->setFilter($groupItem->getFilter());
            $parameterEntity->setCollapsed($groupItem->getCollapsed());
            $parameterEntity->setDisplayNegativeValue($groupItem->getDisplayNegativeValue());
            $parameterEntities[] = $parameterEntity;
            $this->em->persist($parameterEntity);
        }

        return $parameterEntities;
    }

    /**
     * @param array                $parameterEntities
     * @param ProductTypeParameter $entity
     *
     * @return array
     */
    private function getGroupDataForJson(array $parameterEntities, ProductTypeParameter $entity)
    {
        $jsonData[] = $this->getDataForJson($entity);

        /* @var ProductTypeParameter $parameterEntity */
        foreach ($parameterEntities as $parameterEntity) {
            $tempData['type'] = 'parameter';
            $tempData['title'] = $parameterEntity->getParameter()->getTitle();
            $tempData['id'] = $parameterEntity->getId();
            $tempData['id_parameter'] = $parameterEntity->getParameter()->getId();
            $tempData['unit'] = $parameterEntity->getParameter()->getUnit();
            $tempData['id_group'] = $entity->getId();
            $tempData['required'] = $parameterEntity->getRequired();
            $tempData['filter'] = $parameterEntity->getFilter();
            $tempData['collapsed'] = $parameterEntity->getCollapsed();
            $tempData['displayNegativeValue'] = $parameterEntity->getDisplayNegativeValue();
            $jsonData[] = $tempData;
        }

        return $jsonData;
    }
}
