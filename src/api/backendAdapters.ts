import {
  DocumentFieldValue,
  GeneratedDocument,
  TableOptions,
  Template,
  TemplateFormat,
  TemplateStatus,
  TemplateVariable,
  User,
  UserRole,
  VariableType,
} from '../types';

type BackendVariableType =
  | 'text'
  | 'textarea'
  | 'number'
  | 'currency'
  | 'date'
  | 'select'
  | 'boolean'
  | 'table';

interface BackendTemplateListItem {
  id: number;
  name: string;
  category: string;
  format: TemplateFormat;
  status: TemplateStatus;
  tags?: string[];
  created_at: string;
  variables_count: number;
}

interface BackendTemplate extends BackendTemplateListItem {
  variables?: BackendVariable[];
  versions?: Array<{ version_number: string }>;
}

interface BackendVariable {
  id: number;
  key: string;
  label: string;
  type: BackendVariableType;
  required: boolean;
  default_value: string | boolean | null;
  hint: string | null;
  options: string[] | TableOptions | null;
}

interface BackendDocumentListItem {
  id: number;
  template_id?: number;
  template_name: string;
  author_name?: string;
  file_path: string;
  created_at: string;
}

interface BackendDocument extends BackendDocumentListItem {
  values?: Array<{
    variable_key: string;
    value: DocumentFieldValue;
  }>;
}

interface BackendUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  created_at?: string;
}

export function adaptTemplateListItem(item: BackendTemplateListItem): Template {
  return {
    id: String(item.id),
    name: item.name,
    category: item.category,
    tags: item.tags || [],
    format: item.format,
    status: item.status,
    version: 1,
    createdAt: item.created_at,
    variableCount: item.variables_count,
  };
}

export function adaptTemplate(item: BackendTemplate): Template {
  return {
    ...adaptTemplateListItem({
      ...item,
      variables_count: item.variables_count ?? item.variables?.length ?? 0,
    }),
    version: item.versions?.length || 1,
  };
}

export function adaptVariable(templateId: string, variable: BackendVariable): TemplateVariable {
  return {
    id: String(variable.id),
    templateId,
    name: variable.key,
    label: variable.label,
    type: variable.type,
    required: variable.required,
    defaultValue: variable.default_value == null ? '' : String(variable.default_value),
    hint: variable.hint || '',
    options: variable.options || undefined,
  };
}

export function adaptDocument(item: BackendDocument): GeneratedDocument {
  const values = Object.fromEntries(
    (item.values || []).map((value) => [value.variable_key, value.value]),
  );
  const pathParts = item.file_path.split('/');

  return {
    id: String(item.id),
    templateId: String(item.template_id || ''),
    templateName: item.template_name,
    author: item.author_name || '',
    createdAt: item.created_at,
    format: item.file_path.toLowerCase().endsWith('.pdf') ? 'pdf' : 'docx',
    fileName: pathParts[pathParts.length - 1] || `document-${item.id}`,
    values,
  };
}

export function adaptUser(item: BackendUser): User {
  return {
    id: item.id,
    name: item.name,
    email: item.email,
    role: item.role,
    createdAt: item.created_at,
  };
}
