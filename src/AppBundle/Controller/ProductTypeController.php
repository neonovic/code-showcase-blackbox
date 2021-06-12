<?php

namespace AppBundle\Controller;

use AppBundle\Component\Controller\ControllerActionStatus;
use AppBundle\Model\Category\Category;
use AppBundle\Model\Content\Content;
use AppBundle\Model\Parameter\Parameter;
use AppBundle\Model\Parameter\ParameterValue;
use AppBundle\Model\Parameter\ParameterValueFacade;
use AppBundle\Model\Product\ProductType\ProductType;
use AppBundle\Model\Product\ProductType\ProductTypeCategory;
use AppBundle\Model\Product\ProductType\ProductTypeCategoryFacade;
use AppBundle\Model\Product\ProductType\ProductTypeFacade;
use AppBundle\Model\Product\ProductType\ProductTypeParameterFacade;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ProductTypeController.
 *
 * @Route("/producttype")
 */
class ProductTypeController extends AdminController
{
    /**
     * @param Request $request
     * @Route("/parameter/update/checkbox", name="app_parameter_update_checkbox", methods={"POST"})
     *
     * @return JsonResponse
     */
    public function updateParameterCheckboxAction(Request $request): JsonResponse
    {
        $parameter = $request->request->get('parameter', null);
        $parameterId = $request->request->get('parameterId', null);
        $checked = $request->request->get('checked', null);

        $status = new ControllerActionStatus();
        try {
            $this->getProductTypeParameterFacade()->updateCheckedProductTypeParameter($parameter, $parameterId, $checked);
            $status->ok();
        } catch (\Exception $e) {
            $status->error($e->getMessage());
        }

        return $this->json(
            ['status' => $status],
            $status->getStatus()
        );
    }

    /**
     * @param Request $request
     * @Route("/parameter/move", name="app_parameter_move", methods={"POST"})
     *
     * @return JsonResponse
     */
    public function parameterMoveAction(Request $request): JsonResponse
    {
        $currentId = (int)$request->request->get('currentId', null);
        $parentId = (int)$request->request->get('parentId', null);
        $prevId = (int)$request->request->get('prevId', null);

        $parameterFacade = $this->getProductTypeParameterFacade();

        $status = new ControllerActionStatus();
        try {
            $parameterFacade->initMove($currentId, $prevId, $parentId);
            $parameterFacade->processMove();
            $status->ok();
        } catch (\Exception $e) {
            $status->error($e->getMessage());
        }

        return $this->json(
            ['status' => $status],
            $status->getStatus()
        );
    }

    /**
     * @return ProductTypeParameterFacade
     */
    protected function getProductTypeParameterFacade(): ProductTypeParameterFacade
    {
        return new ProductTypeParameterFacade(
            $this->get('doctrine.orm.entity_manager')
        );
    }

    /**
     * Je voláno při zaškrtnutí/odškrtnutí kategorie - tato kategorie se zapíše/smaže z ProductTypeCategory a metoda
     *  vrací nový seznam všech breadcrumbs (breadcrumbs všech aktuálních aktivních categorií).
     *
     * @param Request $request
     * @Route("/set/category", name="app_producttype_set_category", methods={"POST"})
     *
     * @return JsonResponse
     */
    public function setCategoryAndGetBreadcrumbsAction(Request $request): JsonResponse
    {
        $categoryId = $request->request->get('categoryId', null);
        $productTypeId = $request->request->get('entityId', null);
        $checked = $request->request->get('checked', null);

        $status = new ControllerActionStatus();
        try {
            $productTypeCategoryFacade = $this->getProductTypeCategoryFacade();
            $productTypeCategoryFacade->createOrDeleteProductTypeCategory($productTypeId, $categoryId, $checked);
            list($breadcrumbs) = $productTypeCategoryFacade->getBreadcrumbsAndParameters($productTypeId);
            $status->ok();
        } catch (\Exception $e) {
            $breadcrumbs = null;
            $status->error($e->getMessage());
        }

        return $this->json(
            ['status' => $status, 'data' => $breadcrumbs],
            $status->getStatus()
        );
    }

    /**
     * Volá se při vybrání hodnoty parametru u nějaké kategorie - do ProductTypeCategory je zapsána/aktualizována
     *  kombinace "productType, category, parameter, parameterValue".
     *
     * @param Request $request
     * @Route("/set/categoryparameter", name="app_producttype_set_categoryparameter", methods={"POST"})
     *
     * @return JsonResponse
     */
    public function setCategoryAndParameterValuesAction(Request $request): JsonResponse
    {
        $productTypeId = $request->request->get('entityId', null);
        $categoryId = $request->request->get('categoryId', null);
        $parameterId = $request->request->get('parameterId', null);
        $parameterValueId = $request->request->get('parameterValueId', null);

        $status = new ControllerActionStatus();
        try {
            $this->getProductTypeCategoryFacade()->updateProductTypeCategoryParameterValuesForProductTypeIdAndCategoryId(
                $productTypeId,
                $categoryId,
                $parameterId,
                $parameterValueId
            );
            $status->ok();
        } catch (\Exception $e) {
            $status->error($e->getMessage());
        }

        return $this->json(
            ['status' => $status, 'data' => null],
            $status->getStatus()
        );
    }

    /**
     * @param Request $request
     * @Route("/delete/categoryparameter", name="app_producttype_delete_categoryparameter", methods={"POST"})
     *
     * @return JsonResponse
     */
    public function deleteParameterValuesForCategoryAction(Request $request): JsonResponse
    {
        $productTypeId = $request->request->get('entityId', null);
        $categoryId = $request->request->get('categoryId', null);

        $status = new ControllerActionStatus();
        try {
            $this->getProductTypeCategoryFacade()->deleteProductTypeCategoryParameterValuesForProductTypeIdAndCategoryId(
                $productTypeId,
                $categoryId
            );
            $status->ok();
        } catch (\Exception $e) {
            $status->error($e->getMessage());
        }

        return $this->json(
            ['status' => $status, 'data' => null],
            $status->getStatus()
        );
    }

    /**
     * @param Request $request
     * @Route("/get/parametervalues", name="app_producttype_get_parametervalues", methods={"POST"})
     *
     * @return JsonResponse
     */
    public function getProductTypeCategoryParameterValuesAction(Request $request): JsonResponse
    {
        $parameterId = $request->request->get('parameterId', null);

        $status = new ControllerActionStatus();
        $data = null;
        try {
            if (!$parameterId) {
                $status->error('Není definován parameterId.');
            } else {
                $data = $this->getParameterValueFacade()->getFormatedParameterValuesForParameterId($parameterId);
                $status->ok();
            }
        } catch (\Exception $e) {
            $status->error($e->getMessage());
        }

        return $this->json(
            ['status' => $status, 'data' => $data],
            $status->getStatus()
        );
    }

    /**
     * @param Content $entity
     */
    public function preUpdateContentEntity(Content $entity)
    {
        $categoriesCollection = $this->getSubmittedCategoriesCollection();
        $entity->setCategories($categoriesCollection);
    }

    /**
     * @param Content $entity
     */
    public function prePersistContentEntity(Content $entity)
    {
        $categoriesCollection = $this->getSubmittedCategoriesCollection();
        $entity->setCategories($categoriesCollection);
    }

    /**
     * @param ProductType $entity
     */
    public function postUpdateProductTypeEntity(ProductType $entity)
    {
        $this->setProductTypeExtensions($entity);
    }

    /**
     * @param ProductType $entity
     */
    public function postPersistProductTypeEntity(ProductType $entity)
    {
        $this->setProductTypeExtensions($entity);
    }

    /**
     * @param ProductType $entity
     */
    private function setProductTypeExtensions(ProductType $entity)
    {
        $productType = $this->request->get('producttype');
        $upstairs = false;
        if (is_array($productType) && isset($productType['upstairsExtension'])) {
            $upstairs = $productType['upstairsExtension'] ? true : false;
        }
        $ecoDestruct = false;
        if (is_array($productType) && isset($productType['ecoDestructExtension'])) {
            $ecoDestruct = $productType['ecoDestructExtension'] ? true : false;
        }
        $disabledDelivery = [];
        if (is_array($productType) && isset($productType['disabledDeliveryExtension'])) {
            if (is_array($productType['disabledDeliveryExtension']) && !empty($productType['disabledDeliveryExtension'])) {
                $disabledDelivery = $productType['disabledDeliveryExtension'];
            }
        }

        $productTypeFacade = $this->container->get('app.facadefactory')->getFacade(ProductType::class);
        /* @var ProductTypeFacade $productTypeFacade  */
        $productTypeFacade->setEcoDestructExtension($entity, $ecoDestruct);
        $productTypeFacade->setUpstairsExtension($entity, $upstairs);
        $productTypeFacade->setDisabledDeliveryExtension($entity, $disabledDelivery);
    }

    /**
     * @return ArrayCollection
     */
    private function getSubmittedCategoriesCollection(): ArrayCollection
    {
        $categoryRepository = $this->em->getRepository(Category::class);
        $submittedCategoryIds = $this->request->request->get('categories');

        $categoriesCollection = new ArrayCollection();
        if ($submittedCategoryIds) {
            /** @var array $submittedCategoryIds */
            $submittedCategoryIds = array_keys(array_count_values($submittedCategoryIds));
            foreach ($submittedCategoryIds as $id) {
                $categoriesCollection->add($categoryRepository->find($id));
            }
        }

        return $categoriesCollection;
    }

    /**
     * @return ProductTypeCategoryFacade
     */
    private function getProductTypeCategoryFacade(): ProductTypeCategoryFacade
    {
        $facade = new ProductTypeCategoryFacade(
            $this->get('doctrine.orm.entity_manager'),
            $this->get('doctrine.orm.entity_manager')->getRepository(ProductTypeCategory::class),
            $this->container
        );
        $facade->categoryRepository->setShopSelectService($this->container->get('blackbox.shop.select'));

        return $facade;
    }

    /**
     * @return ParameterValueFacade
     */
    private function getParameterValueFacade(): ParameterValueFacade
    {
        $facade = new ParameterValueFacade(
            $this->get('doctrine.orm.entity_manager'),
            $this->get('doctrine.orm.entity_manager')->getRepository(ParameterValue::class),
            $this->container
        );
        $facade->setParameterRepository($this->get('doctrine.orm.entity_manager')->getRepository(Parameter::class));

        return $facade;
    }
}
