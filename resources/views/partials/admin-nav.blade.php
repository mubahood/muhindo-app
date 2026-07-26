@php
  /** @var \App\Models\User $u */
  $u = Auth::user();

  // Gate helper: null = always; 'super' = super-admin; string = permission;
  // array = "any of these permissions".
  $allow = function ($gate) use ($u) {
      if ($gate === null) return true;
      if ($gate === 'super') return $u->isSuperAdmin();
      if (is_array($gate)) {
          foreach ($gate as $p) if ($u->can($p)) return true;
          return false;
      }
      return $u->can($gate);
  };

  $groups = [
      ['key' => 'portfolio', 'label' => 'Portfolio', 'icon' => 'fa-id-card', 'gate' => 'portfolio.manage', 'items' => [
          ['label' => 'Projects', 'icon' => 'fa-diagram-project', 'route' => 'admin.portfolio-projects.index', 'match' => ['admin.portfolio-projects.*']],
          ['label' => 'Skills', 'icon' => 'fa-star', 'route' => 'admin.skills.index', 'match' => ['admin.skills.*']],
          ['label' => 'Experience', 'icon' => 'fa-briefcase', 'route' => 'admin.experience.index', 'match' => ['admin.experience.*']],
          ['label' => 'Education', 'icon' => 'fa-graduation-cap', 'route' => 'admin.education.index', 'match' => ['admin.education.*']],
          ['label' => 'Services', 'icon' => 'fa-list-check', 'route' => 'admin.services.index', 'match' => ['admin.services.*']],
          ['label' => 'Messages', 'icon' => 'fa-envelope', 'route' => 'admin.messages.index', 'match' => ['admin.messages.*']],
      ]],
      ['key' => 'courses', 'label' => 'Courses', 'icon' => 'fa-chalkboard-user', 'gate' => 'courses.manage', 'items' => [
          ['label' => 'Courses', 'icon' => 'fa-book', 'route' => 'admin.courses.index', 'match' => ['admin.courses.*']],
          ['label' => 'Enrollments', 'icon' => 'fa-user-graduate', 'route' => 'admin.enrollments.index', 'match' => ['admin.enrollments.*']],
      ]],
      ['key' => 'work', 'label' => 'Clients & Projects', 'icon' => 'fa-handshake', 'gate' => ['clients.manage', 'projects.manage'], 'items' => [
          ['label' => 'Clients', 'icon' => 'fa-address-book', 'route' => 'admin.clients.index', 'match' => ['admin.clients.*'], 'can' => 'clients.manage'],
          ['label' => 'Projects', 'icon' => 'fa-diagram-project', 'route' => 'admin.projects.index', 'match' => ['admin.projects.*'], 'can' => 'projects.manage'],
      ]],
      ['key' => 'billing', 'label' => 'Billing', 'icon' => 'fa-file-invoice-dollar', 'gate' => 'billing.manage', 'items' => [
          ['label' => 'Invoices', 'icon' => 'fa-file-invoice-dollar', 'route' => 'admin.invoices.index', 'match' => ['admin.invoices.*']],
      ]],
      ['key' => 'system', 'label' => 'System', 'icon' => 'fa-users-gear', 'gate' => ['manage-users', 'manage-settings'], 'items' => [
          ['label' => 'Staff', 'icon' => 'fa-user-shield', 'route' => 'admin.users.index', 'match' => ['admin.users.*'], 'can' => 'manage-users'],
          ['label' => 'Site settings', 'icon' => 'fa-sliders', 'route' => 'admin.settings.index', 'match' => ['admin.settings.index'], 'can' => 'manage-settings'],
      ]],
  ];

  // Resolve visibility + active state, and find which group owns the current page.
  $rendered = [];
  $activeGroup = '';
  foreach ($groups as $g) {
      if (! $allow($g['gate'])) continue;
      $items = [];
      $groupActive = false;
      foreach ($g['items'] as $it) {
          if (isset($it['can']) && ! $allow($it['can'])) continue;
          $it['active'] = request()->routeIs(...$it['match']);
          if ($it['active']) $groupActive = true;
          $items[] = $it;
      }
      if (! $items) continue;
      if ($groupActive) $activeGroup = $g['key'];
      $rendered[] = ['key' => $g['key'], 'label' => $g['label'], 'icon' => $g['icon'], 'active' => $groupActive, 'items' => $items];
  }
@endphp

<ul class="tb-nav-list"
    x-data="{ open: $persist('{{ $activeGroup }}').as('td_nav_open') }"
    x-init="$nextTick(() => { @if($activeGroup) open = '{{ $activeGroup }}' @endif })">

  <li class="tb-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
    <a wire:navigate href="{{ route('dashboard') }}"><i class="fas fa-gauge"></i> Dashboard</a>
  </li>

  @foreach($rendered as $g)
    <li class="tb-nav-group">
      <button type="button" class="tb-nav-gh {{ $g['active'] ? 'has-active' : '' }}"
              :class="{ 'open': open === '{{ $g['key'] }}' }"
              @click="open = (open === '{{ $g['key'] }}' ? '' : '{{ $g['key'] }}')"
              aria-controls="navsub-{{ $g['key'] }}">
        <i class="fas {{ $g['icon'] }} gicon"></i>
        <span class="glabel">{{ $g['label'] }}</span>
        <i class="fas fa-chevron-down gcaret"></i>
      </button>
      <ul class="tb-nav-sub" id="navsub-{{ $g['key'] }}" x-show="open === '{{ $g['key'] }}'" x-collapse x-cloak>
        @foreach($g['items'] as $it)
          <li class="tb-nav-item {{ $it['active'] ? 'active' : '' }}">
            <a wire:navigate href="{{ route($it['route']) }}"><i class="fas {{ $it['icon'] }}"></i> {{ $it['label'] }}</a>
          </li>
        @endforeach
      </ul>
    </li>
  @endforeach
</ul>
