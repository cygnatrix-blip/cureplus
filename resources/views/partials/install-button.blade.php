<div x-data="pwaInstall">
    <button type="button" x-show="showButton" x-cloak @click="install" class="{{ $class ?? 'btn btn-secondary' }}">Install app</button>
    <button type="button" x-show="showIosGuide" x-cloak @click="toggleGuide" class="{{ $class ?? 'btn btn-secondary' }}">Add to Home Screen</button>
    <p x-show="showGuideText" x-cloak class="mt-2 rounded-lg bg-slate-800 p-3 text-xs text-slate-100">In Safari, tap Share, then "Add to Home Screen".</p>
</div>
