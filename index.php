<?php

	/**
	 * Main access point to the app.
	 *
	 * $Id: index.php,v 1.13 2007/04/18 14:08:48 mr-russ Exp $
	 */

	// Include application functions
	$_no_db_connection = true;
	include_once('./libraries/lib.inc.php');

	// Loading-indicator shim: injects a small CSS spinner into the browser (nav)
	// frame and toggles it on any link click that would navigate a frame.
	// Detail frame's onload hides it; a 15s safety timeout catches downloads,
	// javascript: hrefs, and other cases where onload never fires.
	$spinnerScript = <<<'JS'
<script type="text/javascript">
(function () {
	var SAFETY_MS = 15000;
	var safetyTimer = null;

	function ensureSpinner() {
		var f = window.frames['browser'];
		if (!f || !f.document || !f.document.body) return null;
		var doc = f.document;
		var el = doc.getElementById('__ppa_spinner');
		if (!el) {
			var style = doc.createElement('style');
			style.setAttribute('data-ppa', 'spinner');
			style.appendChild(doc.createTextNode(
				'#__ppa_spinner{position:fixed;top:8px;right:8px;width:22px;height:22px;' +
				'border:3px solid rgba(51,103,145,0.2);border-top-color:#336791;' +
				'border-radius:50%;animation:__ppaSpin 0.8s linear infinite;' +
				'z-index:99999;display:none;pointer-events:none;' +
				'box-shadow:0 1px 3px rgba(0,0,0,0.15);background:rgba(255,255,255,0.7)}' +
				'@keyframes __ppaSpin{to{transform:rotate(360deg)}}'
			));
			doc.head.appendChild(style);
			el = doc.createElement('div');
			el.id = '__ppa_spinner';
			el.setAttribute('aria-hidden', 'true');
			doc.body.appendChild(el);
		}
		return el;
	}

	function showSpinner() {
		var el = ensureSpinner();
		if (!el) return;
		el.style.display = 'block';
		if (safetyTimer) clearTimeout(safetyTimer);
		safetyTimer = setTimeout(hideSpinner, SAFETY_MS);
	}
	function hideSpinner() {
		var el = ensureSpinner();
		if (el) el.style.display = 'none';
		if (safetyTimer) { clearTimeout(safetyTimer); safetyTimer = null; }
	}

	function isNavigableLink(a) {
		if (!a || a.tagName !== 'A') return false;
		var href = a.getAttribute('href');
		if (!href) return false;
		if (href.charAt(0) === '#') return false;
		if (/^javascript:/i.test(href)) return false;
		return true;
	}

	function clickHandler(e) {
		var t = e.target;
		var stop = t && t.ownerDocument;
		while (t && t !== stop) {
			if (t.tagName === 'A' && isNavigableLink(t)) {
				showSpinner();
				return;
			}
			t = t.parentNode;
		}
	}

	function bindFrame(frameName) {
		var f = window.frames[frameName];
		if (!f || !f.document) return;
		f.document.removeEventListener('click', clickHandler, true);
		f.document.addEventListener('click', clickHandler, true);
	}

	window.__ppaShowSpinner = showSpinner;
	window.__ppaHideSpinner = hideSpinner;
	window.__ppaBindBrowser = function () { bindFrame('browser'); };
	window.__ppaBindDetail  = function () { bindFrame('detail'); };
})();
</script>
JS;

	$misc->printHeader('', $spinnerScript, true);

	$rtl = (strcasecmp($lang['applangdir'], 'rtl') == 0);

	$cols = $rtl ? '*,'.$conf['left_width'] : $conf['left_width'].',*';
	$mainframe = '<frame src="intro.php" name="detail" id="detail" frameborder="0" onload="parent.__ppaHideSpinner && parent.__ppaHideSpinner(); parent.__ppaBindDetail && parent.__ppaBindDetail();" />'
?>
<frameset cols="<?php echo $cols ?>">

<?php if ($rtl) echo $mainframe; ?>

	<frame src="browser.php" name="browser" id="browser" frameborder="0" onload="parent.__ppaBindBrowser && parent.__ppaBindBrowser();" />

<?php if (!$rtl) echo $mainframe; ?>

	<noframes>
	<body>
		<?php echo $lang['strnoframes'] ?><br />
		<a href="intro.php"><?php echo $lang['strnoframeslink'] ?></a>
	</body>
	</noframes>

</frameset>

<?php
	$misc->printFooter(false);
?>
