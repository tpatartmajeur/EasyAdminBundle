<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Context;

use EasyCorp\Bundle\EasyAdminBundle\Configuration\Configuration;
use EasyCorp\Bundle\EasyAdminBundle\Configuration\UserMenuConfig;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\MenuBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A context object that stores all the config about the current dashboard and resource.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class ApplicationContext
{
    public const ATTRIBUTE_KEY = 'easyadmin_context';

    private $config;
    private $request;
    private $tokenStorage;
    private $dashboardControllerInstance;
    private $menuBuilder;
    private $assets;
    private $crudConfig;
    private $crudPageName;
    private $crudPageContext;
    private $entity;
    private $entityConfig;

    public function __construct(Request $request, TokenStorageInterface $tokenStorage, DashboardControllerInterface $dashboard, MenuBuilderInterface $menuBuilder, AssetContext $assets, ?CrudContext $crudConfig, ?string $crudPageName, ?CrudPageContext $crudPageContext, ?DoctrineEntityContext $entityConfig, $entity)
    {
        $this->request = $request;
        $this->tokenStorage = $tokenStorage;
        $this->dashboardControllerInstance = $dashboard;
        $this->menuBuilder = $menuBuilder;
        $this->assets = $assets;
        $this->crudConfig = $crudConfig;
        $this->crudPageName = $crudPageName;
        $this->crudPageContext = $crudPageContext;
        $this->entityConfig = $entityConfig;
        $this->entity = $entity;

        $userMenuConfig = null === $this->getUser() ? UserMenuConfig::new()->getAsValueObject() : $dashboard->configureUserMenu($this->getUser())->getAsValueObject();
        $dashboardConfig = $dashboard->configureDashboard()->getAsValueObject();
        $this->config = new Configuration($dashboardConfig, $assets, $userMenuConfig, $crudConfig, $crudPageContext, $this->menuBuilder, $request->getLocale());
    }

    public function getConfig(): Configuration
    {
        return $this->config;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getLocale(bool $languageOnly = false): string
    {
        $fullLocale = $this->getRequest()->getLocale();
        $localeLanguage = strtok($fullLocale, '-_');
        $locale = $languageOnly ? $localeLanguage : $fullLocale;

        return empty($locale) ? 'en' : $locale;
    }

    public function getUser(): ?UserInterface
    {
        // The code of this method is copied from https://github.com/symfony/twig-bridge/blob/master/AppVariable.php
        // MIT License - (c) Fabien Potencier <fabien@symfony.com>
        if (null === $tokenStorage = $this->tokenStorage) {
            throw new \RuntimeException('The "user" variable is not available.');
        }

        if (!$token = $tokenStorage->getToken()) {
            return null;
        }

        $user = $token->getUser();

        return \is_object($user) ? $user : null;
    }

    /**
     * @return \EasyCorp\Bundle\EasyAdminBundle\Contracts\MenuItemInterface[]
     */
    public function getMenu(): array
    {
        $mainMenuItems = iterator_to_array($this->dashboardControllerInstance->getMenuItems());

        return $this->menuBuilder->setItems($mainMenuItems)->build();
    }

    public function getSelectedMenuIndex(): ?int
    {
        return $this->getRequest()->query->getInt('menuIndex', -1);
    }

    public function getSelectedSubMenuIndex(): ?int
    {
        return $this->getRequest()->query->getInt('submenuIndex', -1);
    }

    /**
     * Returns the name of the current CRUD page, if any (e.g. 'detail')
     */
    public function getPage(): ?string
    {
        return $this->crudPageName;
    }

    public function getTransParameters(): array
    {
        if (null === $this->crudConfig || null === $this->getEntity()) {
            return [];
        }

        return [
            '%entity_label_singular%' => $this->crudConfig->getLabelInSingular(),
            '%entity_label_plural%' => $this->crudConfig->getLabelInPlural(),
            '%entity_name%' => $this->crudConfig->getLabelInPlural(),
            '%entity_id%' => $this->getEntity()->getIdValue(),
        ];
    }

    public function getEntity(): ?DoctrineEntityContext
    {
        return $this->entityConfig;
    }

    public function getDashboardRouteName(): string
    {
        return $this->request->attributes->get('_route');
    }
}