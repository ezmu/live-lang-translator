<?php

namespace Ezmu\LiveLangTranslator\Translation;

use Illuminate\Translation\Translator;
use Illuminate\Support\HtmlString;

class LiveLangTranslator extends Translator
{
    protected static array $usedTranslations = [];
    protected static array $allTranslations = [];

    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $translated = parent::get($key, $replace, $locale, $fallback);

        if (!request()->has('LiveLang')) {
            return $translated;
        }

        $locale = $locale ?? $this->locale;
        $locales = config('app.supported_locales', [app()->getLocale()]);
        $values = [];

        foreach ($locales as $loc) {
            $values[$loc] = parent::get($key, [], $loc);
        }

        self::$usedTranslations[$key] = $values;
        self::$allTranslations[$key] = json_encode($values);

        $safeKey = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        $hintBtn = "<span class=\"translation-hint-btn\" data-key=\"{$safeKey}\"></span>";

        return new HtmlString($translated . $hintBtn);
    }

   public static function renderFooterScripts(): HtmlString
{
   
    if (empty(self::$allTranslations)) {
        return new HtmlString(''); // nothing to render
    }

    $csrfToken = csrf_token();
    $baseUrl = url('');
$allTranslationsJson = json_encode(self::$allTranslations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $modalHtml = <<<HTML
<!-- Translation Editor Modal -->
<div id="translation-editor" style="display:none;position:fixed;top:10%;left:50%;transform:translateX(-50%);background:#fff;padding:15px;border:1px solid #ccc;z-index:9999;box-shadow:0 4px 10px rgba(0,0,0,0.2);max-width:500px;">
    <h4>Edit Translation: <span id="trans-key" style="color:#005;">...</span></h4>
    <form onsubmit="return saveTranslation(event);">
        <div id="trans-inputs"></div>
        <div style="margin-top:10px;text-align:right;">
            <button type="submit">💾 Save</button>
            <button type="button" onclick="closeTranslationEditor()">❌ Cancel</button>
        </div>
    </form>
</div>

<script>
    
const translations = {$allTranslationsJson};
const csrfToken = '{$csrfToken}';
const baseUrl = '{$baseUrl}';

window.onload = function() {
 
    document.querySelectorAll('span.translation-hint-btn').forEach(function(container) {
        const key = container.dataset.key;
        if (!key || !translations[key]) return;
        if (container.querySelector('button')) return;

        const btn = document.createElement('button');
        btn.textContent = '🌐';
        btn.title = key;
        btn.style.cssText = 'font-size:10px;padding:1px 4px;margin-left:4px;cursor:pointer;';
        btn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            openTranslationEditor(key, translations[key]);
        };
        container.appendChild(btn);
    });
};

function openTranslationEditor(key, jsonValues) {
    const values = JSON.parse(jsonValues);
    const inputsDiv = document.getElementById('trans-inputs');
    inputsDiv.innerHTML = '';
    document.getElementById('trans-key').textContent = key;

    Object.entries(values).forEach(([locale, val]) => {
        val = val ?? '';
        val = val.replace(/"/g, '&quot;'); // escape quotes for input value
        inputsDiv.innerHTML += `
            <label style="display:block;margin-bottom:8px;color:#000;">
                🌐 \${locale}:<br>
                <input name="trans_\${locale}" value="\${val}" style="width:100%;padding:4px;">
            </label>
        `;
    });

    inputsDiv.dataset.key = key;
    document.getElementById('translation-editor').style.display = 'block';
}

function closeTranslationEditor() {
    document.getElementById('translation-editor').style.display = 'none';
}

function saveTranslation(event) {
    event.preventDefault();
    const inputsDiv = document.getElementById('trans-inputs');
    const key = inputsDiv.dataset.key;
    const inputs = inputsDiv.querySelectorAll('input');
    const data = { key };

    inputs.forEach(input => {
        const locale = input.name.replace('trans_', '');
        data[locale] = input.value;
    });

    fetch(baseUrl + '/dev/save-translation', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(res => {
        alert(res.message || 'Saved!');
        closeTranslationEditor();
        window.location.reload();
    })
    .catch(err => alert('Error saving: ' + err));
}
</script>
HTML;

    return new HtmlString($modalHtml);
}


    public static function getUsedTranslationsrows(): array
    {
        return self::$usedTranslations;
    }
}