<?php
/** @var string $currentLocale @var string $currentCurrency */
use App\Core\LocaleCatalog;

$currentLocale = $currentLocale ?? ($_SESSION['_locale'] ?? 'en');
$currentCurrency = strtoupper($currentCurrency ?? ($_SESSION['_currency'] ?? 'USD'));

$localeOptions = LocaleCatalog::locales();
$currencyOptions = LocaleCatalog::currencies();

$suggestedLocale = LocaleCatalog::suggestedLocale();
$suggestedCurrency = LocaleCatalog::suggestedCurrency();
?>
<div class="pref-backdrop" id="prefModal" hidden aria-hidden="true">
    <div class="pref-dialog" role="dialog" aria-modal="true" aria-labelledby="prefTitle">
        <div class="pref-header">
            <h2 id="prefTitle"><?= e(__('pref_title', 'Preferences')) ?></h2>
            <button type="button" class="pref-close" id="prefClose" aria-label="Close">&times;</button>
        </div>

        <div class="pref-tabs" role="tablist">
            <button type="button" class="pref-tab is-active" role="tab" aria-selected="true" data-tab="locale" id="prefTabLocale">
                <?= e(__('pref_tab_locale', 'Region and Language')) ?>
            </button>
            <button type="button" class="pref-tab" role="tab" aria-selected="false" data-tab="currency" id="prefTabCurrency">
                <?= e(__('pref_tab_currency', 'Currency')) ?>
            </button>
        </div>

        <div class="pref-body">
            <div class="pref-panel is-active" data-panel="locale" role="tabpanel" aria-labelledby="prefTabLocale">
                <div class="pref-suggested">
                    <h3><?= e(__('pref_suggested', 'Suggested Region and Language')) ?></h3>
                    <p class="pref-suggested-value">
                        <strong><?= e($suggestedLocale['region']) ?></strong>
                        <span><?= e($suggestedLocale['language']) ?></span>
                    </p>
                </div>

                <h3 class="pref-section-title"><?= e(__('pref_choose_locale', 'Choose a Region and Language')) ?></h3>
                <div class="pref-grid pref-grid-locale">
                    <?php foreach ($localeOptions as $opt): ?>
                        <a href="<?= e(url('/locale/' . $opt['code'])) ?>"
                           class="pref-card<?= $currentLocale === $opt['code'] ? ' is-selected' : '' ?>">
                            <strong><?= e($opt['region']) ?></strong>
                            <span><?= e($opt['language']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pref-panel" data-panel="currency" role="tabpanel" aria-labelledby="prefTabCurrency" hidden>
                <div class="pref-suggested">
                    <h3><?= e(__('pref_suggested_currency', 'Suggested Currency')) ?></h3>
                    <p class="pref-suggested-value">
                        <strong><?= e($suggestedCurrency['region']) ?></strong>
                        <span><?= e($suggestedCurrency['label']) ?> (<?= e($suggestedCurrency['symbol']) ?>)</span>
                    </p>
                </div>

                <h3 class="pref-section-title"><?= e(__('pref_choose_currency', 'Choose a Currency')) ?></h3>
                <div class="pref-grid pref-grid-currency">
                    <?php foreach ($currencyOptions as $opt): ?>
                        <a href="<?= e(url('/currency/' . $opt['code'])) ?>"
                           class="pref-card<?= $currentCurrency === $opt['code'] ? ' is-selected' : '' ?>">
                            <strong><?= e($opt['region']) ?></strong>
                            <span><?= e($opt['label']) ?> (<?= e($opt['symbol']) ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <p class="pref-footnote"><?= e(__('pref_footnote', 'Any changes to the preferences are optional, and will persist through your user session. Prices are shown in your chosen currency for convenience; checkout remains in Philippine Peso (₱).')) ?></p>
    </div>
</div>
