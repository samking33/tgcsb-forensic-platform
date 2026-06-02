<?php
$pageTitle = 'Legal Disclaimer - Android Forensic Tool';
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <h3 class="mb-0"><i class="fas fa-gavel me-2 text-danger"></i>Legal Disclaimer & Terms of Use</h3>
            <p class="text-muted small">Important legal information regarding the use of this forensic tool</p>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Critical Warning -->
            <div class="alert alert-danger shadow-sm border-0 mb-4" role="alert">
                <div class="d-flex align-items-start">
                    <i class="fas fa-exclamation-triangle fa-3x me-3"></i>
                    <div>
                        <h4 class="alert-heading fw-bold">MANDATORY LEGAL NOTICE</h4>
                        <p class="mb-2">
                            This tool is designed for <strong>lawful forensic analysis only</strong>. Unauthorized
                            access to
                            devices or data is <strong>illegal</strong> and may result in criminal prosecution.
                        </p>
                        <p class="mb-0">
                            By using this tool, you certify that you have proper legal authorization and comply with all
                            applicable laws.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Terms Sections -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-danger text-white">
                            <h5 class="card-title text-white mb-0">
                                <i class="fas fa-balance-scale me-2"></i>Legal Authorization Required
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6 class="fw-bold">You MUST have one of the following:</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Valid court order or search warrant
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Written consent from the device owner
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Corporate authorization (if company-owned device)
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Parental/guardian rights (for minor's device)
                                </li>
                            </ul>
                            <div class="alert alert-warning mt-3 mb-0">
                                <strong>Warning:</strong> Accessing someone else's device without authorization is a
                                <strong>federal crime</strong> in most jurisdictions.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shield-alt me-2"></i>Chain of Custody
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6 class="fw-bold">Evidence Handling Requirements:</h6>
                            <ol class="list-group list-group-numbered list-group-flush">
                                <li class="list-group-item">
                                    <strong>Document Everything:</strong> Record who accessed the device, when, and why.
                                </li>
                                <li class="list-group-item">
                                    <strong>Verify Hashes:</strong> Use the hash verification system to prove data
                                    integrity.
                                </li>
                                <li class="list-group-item">
                                    <strong>Maintain Audit Logs:</strong> All actions are logged automatically for court
                                    admissibility.
                                </li>
                                <li class="list-group-item">
                                    <strong>Secure Storage:</strong> Store extracted data in encrypted, tamper-proof
                                    storage.
                                </li>
                                <li class="list-group-item">
                                    <strong>Limit Access:</strong> Only authorized personnel should access forensic
                                    data.
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Privacy & Data Protection -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title text-white mb-0">
                        <i class="fas fa-user-secret me-2"></i>Privacy & Data Protection Compliance
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold">GDPR Compliance (EU)</h6>
                            <p class="small">
                                If processing data of EU citizens, ensure Article 6 lawful basis exists.
                                Data subjects have the right to access, rectification, and erasure.
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold">CCPA Compliance (California)</h6>
                            <p class="small">
                                California residents have specific rights regarding their personal data.
                                Forensic analysis must comply with disclosure requirements.
                            </p>
                        </div>
                    </div>
                    <div class="alert alert-info mb-0 mt-2">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Consult with legal counsel to ensure compliance with applicable privacy
                        laws.
                    </div>
                </div>
            </div>

            <!-- Usage Restrictions -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="card-title text-white mb-0">
                        <i class="fas fa-ban me-2"></i>Prohibited Uses
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center text-danger mb-3">
                                <i class="fas fa-times-circle fa-3x mb-2"></i>
                                <h6 class="fw-bold">Unauthorized Surveillance</h6>
                                <p class="small text-muted">Monitoring without consent</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center text-danger mb-3">
                                <i class="fas fa-times-circle fa-3x mb-2"></i>
                                <h6 class="fw-bold">Corporate Espionage</h6>
                                <p class="small text-muted">Stealing trade secrets</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center text-danger mb-3">
                                <i class="fas fa-times-circle fa-3x mb-2"></i>
                                <h6 class="fw-bold">Stalking/Harassment</h6>
                                <p class="small text-muted">Tracking individuals without authorization</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Disclaimer -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h5 class="card-title text-white mb-0">
                        <i class="fas fa-file-contract me-2"></i>Disclaimer of Liability
                    </h5>
                </div>
                <div class="card-body">
                    <p>
                        This software is provided "AS IS" without warranty of any kind. The developers and distributors
                        of this tool are <strong>not liable</strong> for:
                    </p>
                    <ul>
                        <li>Misuse of the tool for illegal purposes</li>
                        <li>Data loss or corruption during extraction</li>
                        <li>Legal consequences arising from unauthorized use</li>
                        <li>Inaccuracies in analysis results</li>
                        <li>Violations of privacy laws or regulations</li>
                    </ul>
                    <div class="alert alert-dark mb-0 mt-3">
                        <p class="mb-0">
                            <strong>By using this tool, you agree to:</strong><br>
                            (1) Use it lawfully and ethically<br>
                            (2) Obtain proper authorization before analysis<br>
                            (3) Maintain chain of custody for evidence<br>
                            (4) Comply with all applicable laws and regulations<br>
                            (5) Accept full responsibility for your actions
                        </p>
                    </div>
                </div>
            </div>

            <!-- Acknowledgment -->
            <div class="text-center mt-4">
                <a href="<?= $basePath ?>index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-check-circle me-2"></i>I Understand and Accept These Terms
                </a>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>