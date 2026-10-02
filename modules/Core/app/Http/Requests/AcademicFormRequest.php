<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Support\Facades\Gate;

/**
 * Base for every academic-management write: the same helpers and
 * Indonesian messages as master data, gated by `core.academic.manage`.
 */
abstract class AcademicFormRequest extends MasterFormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('core.academic.manage');
    }
}
