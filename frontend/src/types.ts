export type TemplateStatus = 'draft' | 'published' | 'archived';
export type TemplateFormat = 'docx' | 'pdf';
export type UserRole = 'admin' | 'methodologist' | 'user';

export type VariableType =
  | 'text'
  | 'textarea'
  | 'number'
  | 'currency'
  | 'date'
  | 'select'
  | 'boolean'
  | 'table';

export type TableRowValue = Record<string, string>;
export type DocumentFieldValue = string | boolean | TableRowValue[];

export interface TableOptions {
  columns: string[];
}

export interface Template {
  id: string;
  name: string;
  category: string;
  tags: string[];
  format: TemplateFormat;
  status: TemplateStatus;
  version: number;
  createdAt: string;
  variableCount: number;
}

export interface TemplateVariable {
  id: string;
  templateId: string;
  name: string;
  label: string;
  type: VariableType;
  required: boolean;
  defaultValue: string;
  hint: string;
  options?: string[] | TableOptions;
}

export interface GeneratedDocument {
  id: string;
  templateId: string;
  templateName: string;
  author: string;
  createdAt: string;
  format: TemplateFormat | 'pdf';
  fileName: string;
  values: Record<string, DocumentFieldValue>;
}

export interface UploadTemplateInput {
  name: string;
  category: string;
  tags: string[];
  format: TemplateFormat;
  file: File;
}

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  createdAt?: string;
}

export interface AuthCredentials {
  email: string;
  password: string;
}

export interface RegisterInput extends AuthCredentials {
  name: string;
}
