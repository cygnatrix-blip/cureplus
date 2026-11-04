<div x-data="pwaUpdate" x-show="hasUpdate" x-cloak role="status" class="fixed right-4 bottom-4 left-4 z-50 flex items-center justify-between gap-3 rounded-lg bg-slate-900 px-4 py-3 text-sm text-white shadow-xl sm:left-auto">
    <span>Update available</span>
    <button type="button" @click="refresh" class="btn btn-sm btn-primary">Refresh</button>
</div>
