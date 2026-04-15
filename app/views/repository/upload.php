<?php
/**
 * Document Upload View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Upload Document</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="main-container doc-container">
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <?php
                // Set defaults for upload mode
                $mode = $mode ?? 'upload';
                $dID = $dID ?? 0;
                $actionUrl = $actionUrl ?? '/upload';
                $cancelUrl = $cancelUrl ?? '/';
                $submitLabel = $submitLabel ?? 'Submit Document';
                $docData = $docData ?? null;
                $isRevise = ($mode === 'revise_doc');
                $isEditDraft = ($mode === 'edit_draft');

                // Pre-populate values from docData or $_POST
                $valTitle = $_POST['title'] ?? ($docData['title'] ?? '');
                $valAbstract = $_POST['abstract'] ?? ($docData['abstract'] ?? '');
                $valNotes = $_POST['notes'] ?? ($docData['notes'] ?? '');
                $valFullText = $_POST['full_text'] ?? ($docData['full_text'] ?? '');
                $valDtype = $_POST['dtype'] ?? ($docData['dtype'] ?? 1);
                $valTID = $_POST['tID'] ?? ($docData['tID'] ?? 0);
                $valIsOld = $_POST['is_old'] ?? ($docData && !empty($docData['pubdate']) ? '1' : '0');
                $valMainPages = $_POST['main_pages'] ?? ($docData['main_pages'] ?? '');
                $valMainFigs = $_POST['main_figs'] ?? ($docData['main_figs'] ?? '');
                $valMainTabs = $_POST['main_tabs'] ?? ($docData['main_tabs'] ?? '');

                // JS data for pre-populating dynamic rows
                $jsAuthorList = $docData ? json_encode($docData['author_list'] ?? []) : 'null';
                $jsBranches = $docData ? $docData['branches'] : 'null';
                $jsExtLinks = $docData ? json_encode($docData['ext_links'] ?? []) : 'null';
            ?>

            <form action="<?php echo $actionUrl; ?>" method="POST" enctype="multipart/form-data" id="upload-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="author_list_json" id="author_list_json">
                <input type="hidden" name="link_list_json" id="link_list_json">
                <input type="hidden" name="branch_list_json" id="branch_list_json">
                <input type="hidden" name="form_mode" value="<?php echo $mode; ?>">
                <?php if ($dID > 0): ?>
                <input type="hidden" name="dID" value="<?php echo $dID; ?>">
                <?php endif; ?>

                <?php if (!$isRevise): ?>
				<div class="form-group">
					<div class="submission-type-toggle">
						<input type="radio" name="is_old" id="type_new" value="0" 
							   onchange="toggleDateGroup()" <?php echo $valIsOld === '0' ? 'checked' : ''; ?>>
						<label for="type_new" class="submission-type-btn">New Submission</label>

						<input type="radio" name="is_old" id="type_old" value="1" 
							   onchange="toggleDateGroup()" <?php echo $valIsOld === '1' ? 'checked' : ''; ?>>
						<label for="type_old" class="submission-type-btn">Published/Old Document</label>
					</div>
				</div>
                <?php endif; ?>

                <div class="form-group" id="old-date-group" style="display: none;">
                    <div class="date-row">
                        <div>
                            <label>Date Published or Posted as ePrint:</label>
                            <div class="date-inline-group">
                                <input type="date" name="pub_date" id="pub_date" min="1000-01-01" max="9999-12-31" value="<?php echo htmlspecialchars($_POST['pub_date'] ?? ''); ?>">
                            </div>
                            <small>Required for old/publication dates.</small>
                        </div>
                        <div>
                            <label>Date Received in Journal (Optional):</label>
                            <div class="date-inline-group">
                                <input type="date" name="recv_date" id="recv_date" min="1000-01-01" max="9999-12-31" value="<?php echo htmlspecialchars($_POST['recv_date'] ?? ''); ?>">
                            </div>
                            <small>If omitted, Date Published is used.</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="dtype"><h3>Document Type:</h3></label>
                    <select name="dtype" id="dtype" class="form-control" required>
                        <?php foreach ($docTypes as $type): ?>
                            <option value="<?= $type['ID'] ?>" <?= (int)$type['ID'] === (int)$valDtype ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['dtname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="title"><h3>Document Title:</h3></label>
                    <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($valTitle); ?>">
                </div>

                <div class="form-group">
                    <label for="abstract"><h3>Abstract:</h3></label>
                    <textarea id="abstract" name="abstract" rows="6" required><?php echo htmlspecialchars($valAbstract); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="notes"><h3>Notes:</h3></label>
                    <input type="text" id="notes" name="notes" maxlength="255" value="<?php echo htmlspecialchars($valNotes); ?>">
                </div>

                <hr>

                <h3>Affiliations</h3>
                <div id="affiliations-container">
                    <!-- Dynamic affiliation rows -->
                </div>
                <button type="button" class="btn btn-add" onclick="addAffiliationRow()">+ Add Affiliation</button>

                <hr>

                <h3>Authors &amp; Contributions</h3>
                <div class="duty-rules-box">
                    <strong>Duty Assignment Rules:</strong> Each author is assigned a duty percentage reflecting their contribution/responsibility level.
                    <ul>
                        <li><strong>1st-class:</strong> 100% &nbsp;|&nbsp; <strong>other-classified:</strong> 20% &ndash; 99% &nbsp;|&nbsp; <strong>general-unclassified:</strong> 10% each</li>
                        <li>1st-class authors have full responsibility and control of the document</li>
                        <li>The total of all classified duties must not exceed <strong>875%</strong>.</li>
                    </ul>
                </div>

                <div class="batch-add-row">
                    <textarea id="batch-core-ids" rows="1" class="form-control" placeholder="CORE-IDs (e.g., 12A-45B-78C, 65B32A)"></textarea>
                    <input type="text" id="batch-aff-ids" placeholder="Aff. IDs (e.g. 1,2)">
                    <button type="button" id="btn-lookup-authors" class="btn btn-add">Lookup &amp; Add</button>
                </div>

                <div id="authors-container">
                    <!-- Rows generated by JS -->
                </div>

                <div class="author-add-bar">
                    <input type="number" id="manual-insert-pos" min="1" placeholder="Pos." class="author-pos-input">
                    <button type="button" id="btn-add-manual" class="btn btn-secondary">+ Add Manually</button>
                    <button type="button" id="btn-add-myself" class="btn btn-secondary">+ Add Myself</button>
                </div>
                <div class="duty-summary" id="duty-summary">Total Duty: 0%</div>

                <hr>

                <h3>Research Branches</h3>
                <p class="form-hint">Assign 1 to 3 research branches. The total impact must equal 100%.</p>
                <div id="branches-container">
                    <!-- Dynamic branch rows -->
                </div>
                <div class="branch-controls">
                    <button type="button" class="btn btn-add" id="btn-add-branch" onclick="addBranchRow()">+ Add Branch</button>
                    <span class="branch-summary" id="branch-summary">Total Impact: 100%</span>
                </div>

                <hr>

                <div class="form-group">
                    <label for="tID"><h3>Research Topic (optional):</h3></label>
                    <select name="tID" id="tID" class="form-control">
                        <option value="0">-- None --</option>
                        <?php foreach ($researchTopics as $t): ?>
                            <option value="<?php echo $t['tID']; ?>" <?php echo (int)$t['tID'] === (int)$valTID ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['abbr'] . ' — ' . $t['tname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr>

                <h3>External Links</h3>
                <div id="links-container">
                    <!-- Dynamic link rows -->
                </div>
                <button type="button" class="btn btn-add" onclick="addLinkRow()">+ Add Link</button>

                <hr>

                <div class="file-section">
                    <h3>Attach Files</h3>
                    <?php if ($isEditDraft || $isRevise): ?>
                    <div class="form-hint">
                        <?php
                        if ($isEditDraft) {
                            $hasMain = ($docData['has_file'] ?? 0) >= 1;
                            $supplType = ($docData['has_file'] ?? 0) === 2 ? 'PDF' : (($docData['has_file'] ?? 0) === 3 ? 'ZIP' : null);
                        } else {
                            $hasMain = ($docData['version'] ?? 0) >= 1;
                            $supplType = ($docData['ver_suppl'] ?? 0) >= 1 ? (($docData['suppl_ext'] ?? 0) === 2 ? 'ZIP' : 'PDF') : null;
                        }
                        ?>
                        Current file: <strong><?php echo $hasMain ? 'Main PDF attached' : 'None'; ?>
                        <?php if ($supplType): ?> + Supplemental <?php echo $supplType; ?><?php endif; ?></strong>
                        — upload new files below to replace.
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="main_file">Main Document (PDF):</label>
                        <input type="file" id="main_file" name="main_file" accept=".pdf" onchange="toggleFullText()">
                        
                        <div class="metrics-group">
                            <div>
                                <label for="main_pages">Pages (Main):</label>
                                <input type="number" name="main_pages" id="main_pages" min="1" class="form-control" value="<?php echo htmlspecialchars((string)$valMainPages); ?>">
                            </div>
                            <div>
                                <label for="main_figs">Figures:</label>
                                <input type="number" name="main_figs" id="main_figs" min="0" class="form-control" value="<?php echo htmlspecialchars((string)$valMainFigs); ?>">
                            </div>
                            <div>
                                <label for="main_tabs">Tables:</label>
                                <input type="number" name="main_tabs" id="main_tabs" min="0" class="form-control" value="<?php echo htmlspecialchars((string)$valMainTabs); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="supplemental_file">Supplemental File (PDF or ZIP):</label>
                        <input type="file" id="supplemental_file" name="supplemental_file" accept=".pdf,.zip" onchange="toggleFullText()">
                    </div>
                </div>

                <?php if ($isRevise): ?>
                <div class="form-group">
                    <label for="revision_notes"><h3>Revision Notes:</h3></label>
                    <input id="revision_notes" type="text" name="revision_notes" maxlength="255" placeholder="Describe what changed in this revision..." value="<?php echo htmlspecialchars($_POST['revision_notes'] ?? ''); ?>">
                </div>
                <?php endif; ?>

                <div class="form-group" id="full-text-group" style="display: none;">
                    <label for="full_text">Full Text (if no file attached):</label>
                    <textarea id="full_text" name="full_text" rows="8"><?php echo htmlspecialchars($valFullText); ?></textarea>
                </div>

                <div class="submit-group">
                    <?php if ($mode === 'upload'): ?>
                        <button type="submit" name="action" value="draft" class="btn btn-draft">Save as Draft</button>
                        <button type="submit" name="action" value="submit" class="btn btn-submit" onclick="return validateDuty()">Submit Document</button>
                    <?php elseif ($mode === 'edit_draft'): ?>
                        <button type="submit" name="action" value="draft" class="btn btn-draft">Update Draft</button>
                        <a href="<?php echo $cancelUrl; ?>" class="btn btn-secondary">Cancel</a>
                    <?php elseif ($mode === 'revise_doc'): ?>
                        <button type="submit" name="action" value="update" class="btn btn-submit">Update Document</button>
                        <a href="<?php echo $cancelUrl; ?>" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>

<script>
const MAX_UPLOAD_SIZE = <?php echo defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 10485760; ?>;
const AVAILABLE_SOURCES = <?php echo json_encode($availableSources ?? []); ?>;
const USER_DATA = {
    mID: <?php echo $_SESSION['mID'] ?? 'null'; ?>,
    display_name: <?php echo json_encode($_SESSION['display_name'] ?? ''); ?>,
    pub_name: <?php echo json_encode($_SESSION['pub_name'] ?? ''); ?>,
    core_id: <?php echo json_encode($_SESSION['core_id'] ?? ''); ?>
};
const EDIT_AUTHORS = <?php echo $jsAuthorList; ?>;
const EDIT_BRANCHES = <?php echo $jsBranches; ?>;
const EDIT_LINKS = <?php echo $jsExtLinks; ?>;
const BRANCHES_DATA = <?php echo json_encode($researchBranches ?? []); ?>;
const DOC_BRANCH_MAX = <?php echo defined('DOC_BRANCH_MAX') ? DOC_BRANCH_MAX : 3; ?>;
</script>
<script src="/js/upload.js"></script>
</body>
</html>
