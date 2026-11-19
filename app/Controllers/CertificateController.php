<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : CertificateController.php
 * Location    : app/Controllers/
 * Description : Certificate Module Aggregator & Backward-Compatible Facade.
 *
 * Architecture Note:
 * -------------------------------------------------------------------------
 * The Certificate module has been modularized into 3 specialized controllers:
 * 1. CertificateTemplateController   — Templates, Uploads & Visual Designer
 * 2. CertificateGenerationController — Generation, Reports, Preflight & Queue
 * 3. CertificateDownloadController   — Single & Bulk ZIP / PDF Streaming
 *
 * This class acts as a high-performance facade delegating incoming calls to
 * the respective specialized controllers, ensuring 100% backward compatibility
 * with all existing routes, views, AJAX calls, and external invocations.
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

final class CertificateController extends BaseController
{
    private CertificateTemplateController   $templateCtrl;
    private CertificateGenerationController $generationCtrl;
    private CertificateDownloadController   $downloadCtrl;

    public function __construct()
    {
        $this->templateCtrl   = new CertificateTemplateController();
        $this->generationCtrl = new CertificateGenerationController();
        $this->downloadCtrl   = new CertificateDownloadController();
    }

    // =========================================================================
    // Template Management (Delegates to CertificateTemplateController)
    // =========================================================================

    public function index(): void
    {
        $this->templateCtrl->index();
    }

    public function create(): void
    {
        $this->templateCtrl->create();
    }

    public function store(): void
    {
        $this->templateCtrl->store();
    }

    public function designer(): void
    {
        $this->templateCtrl->designer();
    }

    public function saveDesign(): void
    {
        $this->templateCtrl->saveDesign();
    }

    public function editTemplate(): void
    {
        $this->templateCtrl->editTemplate();
    }

    public function updateTemplate(): void
    {
        $this->templateCtrl->updateTemplate();
    }

    public function activateTemplate(): void
    {
        $this->templateCtrl->activateTemplate();
    }

    public function deactivateTemplate(): void
    {
        $this->templateCtrl->deactivateTemplate();
    }

    public function duplicateTemplate(): void
    {
        $this->templateCtrl->duplicateTemplate();
    }

    public function archiveTemplate(): void
    {
        $this->templateCtrl->archiveTemplate();
    }

    public function unarchiveTemplate(): void
    {
        $this->templateCtrl->unarchiveTemplate();
    }

    public function deleteTemplate(): void
    {
        $this->templateCtrl->deleteTemplate();
    }

    public function preview(): void
    {
        $this->templateCtrl->preview();
    }

    public function previewPdf(): void
    {
        $this->templateCtrl->previewPdf();
    }

    public function designerPreview(): void
    {
        $this->templateCtrl->designerPreview();
    }

    public function setEventTemplate(): void
    {
        $this->templateCtrl->setEventTemplate();
    }

    // =========================================================================
    // Certificate Generation (Delegates to CertificateGenerationController)
    // =========================================================================

    public function generate(): void
    {
        $this->generationCtrl->generate();
    }

    public function runGeneration(): void
    {
        $this->generationCtrl->runGeneration();
    }

    public function generationReport(): void
    {
        $this->generationCtrl->generationReport();
    }

    public function ajaxPreflight(): void
    {
        $this->generationCtrl->ajaxPreflight();
    }

    public function generated(): void
    {
        $this->generationCtrl->generated();
    }

    public function ajaxGetEvents(): void
    {
        $this->generationCtrl->ajaxGetEvents();
    }

    public function ajaxGetResults(): void
    {
        $this->generationCtrl->ajaxGetResults();
    }

    public function symposiumBatch(): void
    {
        $this->generationCtrl->symposiumBatch();
    }

    public function runSymposiumBatch(): void
    {
        $this->generationCtrl->runSymposiumBatch();
    }

    // =========================================================================
    // Certificate Downloads (Delegates to CertificateDownloadController)
    // =========================================================================

    public function download(): void
    {
        $this->downloadCtrl->download();
    }

    public function view(): void
    {
        $this->downloadCtrl->view();
    }

    public function downloadZip(): void
    {
        $this->downloadCtrl->downloadZip();
    }

    public function downloadEventZip(): void
    {
        $this->downloadCtrl->downloadEventZip();
    }

    public function downloadWinnersZip(): void
    {
        $this->downloadCtrl->downloadWinnersZip();
    }

    public function downloadParticipantsZip(): void
    {
        $this->downloadCtrl->downloadParticipantsZip();
    }

    public function downloadSymposiumPackage(): void
    {
        $this->downloadCtrl->downloadSymposiumPackage();
    }

    public function downloadSymposiumPdf(): void
    {
        $this->downloadCtrl->downloadSymposiumPdf();
    }

    // =========================================================================
    // Fallback Magic Method for Dynamic / Extension Invocations
    // =========================================================================

    public function __call(string $name, array $arguments): mixed
    {
        if (method_exists($this->templateCtrl, $name)) {
            return $this->templateCtrl->$name(...$arguments);
        }

        if (method_exists($this->generationCtrl, $name)) {
            return $this->generationCtrl->$name(...$arguments);
        }

        if (method_exists($this->downloadCtrl, $name)) {
            return $this->downloadCtrl->$name(...$arguments);
        }

        throw new \BadMethodCallException("Method {$name} does not exist on any certificate controller.");
    }
}
