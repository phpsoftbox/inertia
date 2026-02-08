<?php

declare(strict_types=1);

namespace PhpSoftBox\Inertia\Share;

use PhpSoftBox\Inertia\Page\Breadcrumbs;
use PhpSoftBox\Inertia\Page\PageMeta;
use PhpSoftBox\Inertia\Page\Tabs;
use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function is_array;

class InertiaBaseDataProvider implements SharedDataProviderInterface
{
    public function __construct(
        protected readonly ?SessionInterface $session = null,
        protected readonly ?Breadcrumbs $breadcrumbs = null,
        protected readonly ?PageMeta $meta = null,
        protected readonly ?Tabs $tabs = null,
    ) {
    }

    public function share(ServerRequestInterface $request): array
    {
        $shared = [];

        $user = $request->getAttribute('user');
        if (is_array($user)) {
            $shared['auth'] = [
                'user' => $user,
            ];
        }

        if ($this->session === null) {
            return $this->appendPageData($shared);
        }

        $errors = $this->session->getFlash('errors', []);

        if (is_array($errors) && $errors !== []) {
            $shared['errors'] = $errors;
        }

        $flash = $this->getFlashMessages($request);
        if ($flash !== []) {
            $shared['flash'] = $flash;
        }

        return $this->appendPageData($shared);
    }

    /**
     * @param array<string, mixed> $shared
     * @return array<string, mixed>
     */
    protected function appendPageData(array $shared): array
    {
        if ($this->breadcrumbs !== null && !$this->breadcrumbs->isEmpty()) {
            $shared['breadcrumbs'] = $this->breadcrumbs->all();
        }

        if ($this->meta !== null && !$this->meta->isEmpty()) {
            $shared['meta'] = $this->meta->toArray();
        }

        if ($this->tabs !== null && !$this->tabs->isEmpty()) {
            $shared['tabs'] = $this->tabs->toArray();
        }

        return $shared;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getFlashMessages(ServerRequestInterface $request): array
    {
        if ($this->session === null) {
            return [];
        }

        $data = [
            'success' => $this->session->getFlash('success'),
            'danger'  => $this->session->getFlash('danger'),
            'warning' => $this->session->getFlash('warning'),
            'info'    => $this->session->getFlash('info'),
        ];

        return array_filter($data, static fn (mixed $item): bool => $item !== null);
    }
}
