<div>
  <div class="tb-page-header">
    <div>
      <h1>Gradebook</h1>
      <div class="tb-breadcrumb">
        <a href="{{ route('admin.courses.show', $course) }}">{{ $course->title }}</a> <span>/</span> Gradebook
      </div>
    </div>
    <a href="{{ route('admin.courses.gradebook.export', $course) }}" class="btn-tb btn-tb-primary btn-tb-sm">
      <i class="fas fa-file-csv"></i> Export CSV
    </a>
  </div>

  <div class="tb-card">
    <div class="tb-table-wrap">
      <table class="tb-table">
        <thead>
          <tr>
            <th>Student</th>
            @foreach($items as $item)
              <th>{{ $item['title'] }}</th>
            @endforeach
            <th>Course grade</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
            <tr>
              <td>{{ $row['enrollment']->user->name }}</td>
              @foreach($row['grades'] as $percent)
                <td>{{ $percent !== null ? rtrim(rtrim(number_format($percent, 1), '0'), '.').'%' : '—' }}</td>
              @endforeach
              <td><strong>{{ $row['course_grade'] !== null ? rtrim(rtrim(number_format($row['course_grade'], 1), '0'), '.').'%' : '—' }}</strong></td>
            </tr>
          @empty
            <tr><td colspan="{{ count($items) + 2 }}"><div class="tb-empty" style="padding:30px;"><p>No active or completed enrollments yet.</p></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
