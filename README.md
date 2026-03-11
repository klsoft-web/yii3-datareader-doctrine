# YII3-DATAREADER-DOCTRINE

The package provides a [Yii 3 data reader](https://github.com/yiisoft/data?tab=readme-ov-file#reading-data) that uses the [Doctrine ORM](https://www.doctrine-project.org/).

## Requirement

 - PHP 8.2 or higher.

## Installation

```bash
composer require yii3-datareader-doctrine
```

## How to use

Example:

```php
use App\Data\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Yiisoft\Data\Reader\Filter\AndX;
use Klsoft\Yii3DataReaderDoctrine\Filter\ObjectEquals;
use Yiisoft\Data\Reader\Sort;
use Klsoft\Yii3DataReaderDoctrine\DoctrineDataReader;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class UserController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WebViewRenderer        $viewRenderer)
    {
    }

    public function list(ServerRequestInterface $request): ResponseInterface
    {
        return $this->viewRenderer->render(
            __DIR__ . '/list_template',
            [
                'dataReader' => (new DoctrineDataReader(
                    $this->entityManager,
                    User::class,
                    ['id', 'name', 'email']))
                    ->withFilter(new AndX(
                            new ObjectEquals('id', 1)
                        )
                    )
                    ->withOffset(0)
                    ->withLimit(20)
                    ->withSort(Sort::any()
                    ->withOrder(['id' => 'asc']))
            ]
        );
    }
}
```

Example of using the DoctrineDataReader with the GridView from the [yiisoft/yii-dataview](https://github.com/yiisoft/yii-dataview) package

UserController.php:

```php
use App\Data\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Yiisoft\Data\Reader\Sort;
use Klsoft\Yii3DataReaderDoctrine\DoctrineDataReader;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class UserController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WebViewRenderer        $viewRenderer)
    {
    }

    public function list(ServerRequestInterface $request): ResponseInterface
    {
        return $this->viewRenderer->render(
            __DIR__ . '/list_template',
            [
                'dataReader' => (new DoctrineDataReader(
                    $this->entityManager,
                    User::class,
                    ['id', 'name', 'email']))
                    ->withSort(Sort::any(['id', 'name'])
                    ->withOrder(['name' => 'asc']))
            ]
        );
    }
}
```

list_template.php:

```php
<?php

declare(strict_types=1);

use Yiisoft\View\WebView;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;
use Yiisoft\Yii\DataView\GridView\GridView;
use Yiisoft\Yii\DataView\YiiRouter\UrlParameterProvider;
use Yiisoft\Yii\DataView\YiiRouter\UrlCreator;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\DataReaderInterface;

/**
 * @var WebView $this
 * @var CurrentRoute $currentRoute
 * @var UrlGeneratorInterface $urlGenerator
 * @var DataReaderInterface $dataReader
 */

$this->setTitle('Users');
?>
<h1>Users</h1>
<?= GridView::widget()
    ->dataReader((new OffsetPaginator($dataReader))->withPageSize(20))
    ->urlParameterProvider(new UrlParameterProvider($currentRoute))
    ->urlCreator(new UrlCreator($urlGenerator))
    ->columns(
        new DataColumn(
            property: 'id',
            filter: true
        ),
        new DataColumn(
            property: 'name',
            filter: true
        ),
        new DataColumn(
            property: 'email',
            filter: true
        )
    )
?>
```
