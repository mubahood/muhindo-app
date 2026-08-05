{{-- Global toast host. Livewire components fire: $this->dispatch('toast', message: '...', type: 'success'|'error'|'info').
     Livewire 3 also emits a window CustomEvent, so Alpine catches it via @toast.window. --}}
<div class="tb-toast-host" aria-live="polite" aria-atomic="true"
     x-data="{
       toasts: [],
       add(detail){
         const id = Date.now() + Math.random();
         this.toasts.push({ id, message: detail.message ?? '', type: detail.type ?? 'success' });
         setTimeout(() => this.remove(id), detail.timeout ?? 4200);
       },
       remove(id){ this.toasts = this.toasts.filter(t => t.id !== id); },
       icon(type){ return { success:'fa-circle-check', error:'fa-circle-exclamation', info:'fa-circle-info', warning:'fa-triangle-exclamation' }[type] || 'fa-circle-check'; }
     }"
     @toast.window="add($event.detail)"
     x-init="$nextTick(() => {
       @if(session('success')) add({ message: @js(session('success')), type: 'success' }); @endif
       @if(session('error')) add({ message: @js(session('error')), type: 'error' }); @endif
       @if(session('status')) add({ message: @js(session('status')), type: 'info' }); @endif
     })">
  <template x-for="t in toasts" :key="t.id">
    <div class="tb-toast" :class="'tb-toast-' + t.type" x-transition.opacity.duration.200ms role="status">
      <i class="fas" :class="icon(t.type)"></i>
      <span x-text="t.message"></span>
      <button type="button" class="tb-toast-x" @click="remove(t.id)" aria-label="Dismiss"><i class="fas fa-xmark"></i></button>
    </div>
  </template>
</div>
