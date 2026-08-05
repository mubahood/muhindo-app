{{--
  A system's screenshot, or an honest placeholder where none has been drawn.

  @param  \App\Models\PortfolioProject  $project
--}}
@php $shot = $project->screenshotUrl(); @endphp

@if($shot)
  <img src="{{ $shot }}" alt="{{ $project->title }}, the system in use"
       width="1600" height="1000" loading="lazy" decoding="async">
@else
  <x-ph :src="'images/systems/'.$project->slug.'.svg'"
        :alt="$project->title.' screenshot'"
        label="Screenshot" size="1600 × 1000px" ratio="16 / 10" icon="fa-desktop" />
@endif
