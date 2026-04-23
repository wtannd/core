<?php
/**
 * Document Upload View
 */
    // Set defaults for upload mode
    $mode = $mode ?? 'upload';
    $dID = $dID ?? 0;
    $actionUrl = $actionUrl ?? '/upload';
    $cancelUrl = $cancelUrl ?? '/';
    $docData = $docData ?? null;
    $isRevise = ($mode === 'revise_doc');
    $isEditDraft = ($mode === 'edit_draft');
    $isBlockDisabled = ($mode === 'revise_doc' || $mode === 'edit_draft');
    $mainSize = $mainSize ?? '';
    $supplSize = $supplSize ?? '';
    $supplExt = $docData['suppl_ext'] ?? '';

    // Pre-populate values from docData or $_POST
    $valTitle = $_POST['title'] ?? ($docData['title'] ?? '');
    $valAbstract = $_POST['abstract'] ?? ($docData['abstract'] ?? '');
    $valNotes = $_POST['notes'] ?? ($docData['notes'] ?? '');
    $valFullText = $_POST['full_text'] ?? ($docData['full_text'] ?? '');
    $valDtype = $_POST['dtype'] ?? ($docData['dtype'] ?? 1);
    $valTID = $_POST['tID'] ?? ($docData['tID'] ?? 0);
    $valIsOld = $_POST['is_old'] ?? (!empty($docData['pub_date']) ? '1' : '0');
    $valMainPages = $_POST['main_pages'] ?? ($docData['main_pages'] ?? '');
    $valMainFigs = $_POST['main_figs'] ?? ($docData['main_figs'] ?? '');
    $valMainTabs = $_POST['main_tabs'] ?? ($docData['main_tabs'] ?? '');
    $valRevNotes = $_POST['revision_notes'] ?? '';
    $valPubDate = $_POST['pub_date'] ?? ($docData['pub_date'] ?? '');
    $valRecvDate = $_POST['recv_date'] ?? ($docData['recv_date'] ?? '');

    // JS data for pre-populating dynamic rows
    $jsAuthorList = $_POST['author_list_json'] ?? ($docData['author_list'] ?? 'null');
    $jsBranches = $_POST['branch_list_json'] ?? ($docData['branch_list'] ?? 'null');
    $jsExtLinks = $_POST['link_list_json'] ?? ($docData['link_list'] ?? 'null');
$pageTitle = 'Upload Document';
?>
<?php include VIEWS_PATH_TRIMMED . '/partials/head.php'; ?>
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

            <form action="<?php echo $actionUrl; ?>" method="POST" enctype="multipart/form-data" id="upload-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="author_list_json" id="author_list_json" <?php if ($isBlockDisabled) echo 'disabled="disabled"' ?>>
                <input type="hidden" name="link_list_json" id="link_list_json" <?php if ($isBlockDisabled) echo 'disabled="disabled"' ?>>
                <input type="hidden" name="branch_list_json" id="branch_list_json" <?php if ($isBlockDisabled) echo 'disabled="disabled"' ?>>
                <?php if ($dID > 0): ?>
                <input type="hidden" name="dID" value="<?php echo $dID; ?>">
                <?php endif; ?>

                <?php if (!$isRevise): ?>
                <?php
                    $blockId = 'block-isold';
                    $blockTitle = 'Submission Type:';
                    include VIEWS_PATH_TRIMMED . '/partials/block_begin.php';
                ?>
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

                <div class="form-group" id="old-date-group" style="display: <?php echo $valIsOld === '1' ? 'block' : 'none'; ?>;">
                    <div class="date-row">
                        <div>
                            <label>Date Published or Posted as ePrint:</label>
                            <div class="date-inline-group">
                                <input type="date" name="pub_date" id="pub_date" min="1000-01-01" max="9999-12-31" value="<?php echo htmlspecialchars($valPubDate); ?>">
                            </div>
                            <small>Required for old/publication dates.</small>
                        </div>
                        <div>
                            <label>Date Received in Journal (Optional):</label>
                            <div class="date-inline-group">
                                <input type="date" name="recv_date" id="recv_date" min="1000-01-01" max="9999-12-31" value="<?php echo htmlspecialchars($valRecvDate); ?>">
                            </div>
                            <small>If omitted, Date Published is used.</small>
                        </div>
                    </div>
                </div>
                <?php include VIEWS_PATH_TRIMMED . '/partials/block_end.php'; ?>
                <?php endif; ?>

                <?php
                    $blockId = 'block-dtype';
                    $blockTitle = 'Document Type:';
                    include VIEWS_PATH_TRIMMED . '/partials/block_begin.php';
                ?>
                <div class="form-group">
                    <select name="dtype" id="dtype" class="form-control" required>
                        <?php foreach ($docTypes as $type): ?>
                            <option value="<?= $type['ID'] ?>" <?= (int)$type['ID'] === (int)$valDtype ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['dtname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php include VIEWS_PATH_TRIMMED . '/partials/block_end.php'; ?>

                <?php
                    $blockId = 'block-title';
                    $blockTitle = 'Document Title:';
                    include VIEWS_PATH_TRIMMED . '/partials/block_begin.php';
                ?>
                <div class="form-group">
                    <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($valTitle); ?>">
                </div>
                <?php include VIEWS_PATH_TRIMMED . '/partials/block_end.php'; ?>

                <?php
                    $blockId = 'block-abs';
                    $blockTitle = 'Abstract:';
                    include VIEWS_PATH_TRIMMED . '/partials/block_begin.php';
                ?>
                <div class="form-group">
                    <textarea id="abstract" name="abstract" rows="6" required><?php echo htmlspecialchars($valAbstract); ?></textarea>
                </div>
                <?php include VIEWS_PATH_TRIMMED . '/partials/block_end.php'; ?>

                <?php
                    $blockId = 'block-notes';
                    $blockTitle = 'Notes:';
                    include VIEWS_PATH_TRIMMED . '/partials/block_begin.php';
                ?>
                <div class="form-group">
                    <input type="text" id="notes" name="notes" maxlength="255" value="<?php echo htmlspecialchars($valNotes); ?>">
                </div>
                <?php include VIEWS_PATH_TRIMMED . '/partials/block_end.php'; ?>

                <hr>

                <?php
                    $blockId = 'block-authors';
                    $blockTitle = 'Authors & Affiliations:';
                    include VIEWS_PATH_TRIMMED . '/partials/block_begin.php';
                ?>
                <h3>List of Affiliations -</h3>
                <div id="affiliations-container">
                    <!-- Dynamic affiliation rows -->
                </div>
                <button type="button" class="btn btn-add" onclick="addAffiliationRow()">+ Add Affiliation</button>

                <hr>

                <h3>List of Authors &amp; Contributions -</h3>
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
                <?php include VIEWS_PATH_TRIMMED . '/partials/block_end.php'; ?>

                <hr>

                <?php
                    $blockId = 'block-branches';
                    $blockTitle = 'Research Branches:';
                    include VIEWS_PATH_TRIMMED . '/partials/block_begin.php';
                ?>
                <p class="form-hint">Assign 1 to 3 research branches. The total impact must equal 100%.</p>
                <div id="branches-container">
                    <!-- Dynamic branch rows -->
                </div>
                <div class="branch-controls">
                    <button type="button" class="btn btn-add" id="btn-add-branch" onclick="addBranchRow()">+ Add Branch</button>
                    <span class="branch-summary" id="branch-summary">Total Impact: 100%</span>
                </div>
                <?php include VIEWS_PATH_TRIMMED . '/partials/block_end.php'; ?>

                <hr>

                <?php
                    $blockId = 'block-topic';
                    $blockTitle = 'Research Topic (optional):';
                    include VIEWS_PATH_TRIMMED . '/partials/block_begin.php';
                ?>
                <div class="form-group">
                    <select name="tID" id="tID" class="form-control">
                        <option value="0">-- None --</option>
                        <?php foreach ($researchTopics as $t): ?>
                            <option value="<?php echo $t['tID']; ?>" <?php echo (int)$t['tID'] === (int)$valTID ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['abbr'] . ' — ' . $t['tname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php include VIEWS_PATH_TRIMMED . '/partials/block_end.php'; ?>

                <hr>

                <?php
                    $blockId = 'block-links';
                    $blockTitle = 'External Links:';
                    include VIEWS_PATH_TRIMMED . '/partials/block_begin.php';
                ?>
                <div id="links-container">
                    <!-- Dynamic link rows -->
                </div>
                <button type="button" class="btn btn-add" onclick="addLinkRow()">+ Add Link</button>
                <?php include VIEWS_PATH_TRIMMED . '/partials/block_end.php'; ?>

                <hr>

                <?php
                    $blockId = 'block-files';
                    $blockTitle = 'Attach Files:';
                    include VIEWS_PATH_TRIMMED . '/partials/block_begin.php';
                ?>
                <div class="file-section">
                    <?php if ($isEditDraft || $isRevise): ?>
                    <div class="form-hint">
                        Current file(s): <strong><?php echo $mainSize ? 'Main PDF (' . $mainSize . ')' : 'None'; ?>
                        <?php echo $supplSize ? ' + Supplemental '. strtoupper($supplExt) . ' (' . $supplSize . ')' : ''; ?></strong> attached.
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
                    <input id="revision_notes" type="text" name="revision_notes" maxlength="255" placeholder="Describe what changed in this revision..." value="<?php echo htmlspecialchars($valRevNotes); ?>">
                </div>
                <?php endif; ?>
                <?php include VIEWS_PATH_TRIMMED . '/partials/block_end.php'; ?>

                <?php if (empty($mainSize) && empty($supplSize)): ?>
                <?php
                    $blockId = 'block-fulltext';
                    $blockTitle = 'Full Text (if no file attached):';
                    include VIEWS_PATH_TRIMMED . '/partials/block_begin.php';
                ?>
                <div class="form-group" id="full-text-group">
                    <textarea id="full_text" name="full_text" rows="8"><?php echo htmlspecialchars($valFullText); ?></textarea>
                </div>
                <?php include VIEWS_PATH_TRIMMED . '/partials/block_end.php'; ?>
                <?php endif; ?>

                <div class="submit-group">
                    <?php if ($mode === 'upload'): ?>
                        <button type="submit" name="action" value="save" class="btn btn-draft">Save as Draft</button>
                        <button type="submit" name="action" value="submit" class="btn btn-submit" onclick="return validateDuty()">Submit Document</button>
                    <?php elseif ($mode === 'edit_draft'): ?>
                        <button type="submit" name="action" value="edit" class="btn btn-draft">Update Draft</button>
                        <a href="<?php echo $cancelUrl; ?>" class="btn btn-secondary">Cancel</a>
                    <?php elseif ($mode === 'revise_doc'): ?>
                        <button type="submit" name="action" value="revise" class="btn btn-submit">Update Document</button>
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
    mID: <?php echo json_encode($_SESSION['mID'] ?? 'null'); ?>,
    pub_name: <?php echo json_encode($_SESSION['pub_name'] ?? ''); ?>,
    Core_ID: <?php echo json_encode($_SESSION['Core_ID'] ?? ''); ?>
};
const EDIT_AUTHORS = <?php echo $jsAuthorList; ?>;
const EDIT_BRANCHES = <?php echo $jsBranches; ?>;
const EDIT_LINKS = <?php echo $jsExtLinks; ?>;
const BRANCHES_DATA = <?php echo json_encode($researchBranches ?? []); ?>;
const DOC_BRANCH_MAX = <?php echo defined('DOC_BRANCH_MAX') ? DOC_BRANCH_MAX : 3; ?>;
</script>
<script src="/js/upload.min.js"></script>
</body>
</html>
