import {
  DocumentFieldValue,
  GeneratedDocument,
  Template,
  TemplateVariable,
  UploadTemplateInput,
} from '../types';
import { seedDocuments, seedTemplates, seedVariables } from './mockData';

const STORAGE_KEYS = {
  templates: 'contract_templates',
  variables: 'contract_template_variables',
  documents: 'contract_documents',
};

const wait = (ms = 250) => new Promise((resolve) => window.setTimeout(resolve, ms));

function readStore<T>(key: string, seed: T): T {
  const raw = window.localStorage.getItem(key);
  if (!raw) {
    window.localStorage.setItem(key, JSON.stringify(seed));
    return seed;
  }
  return JSON.parse(raw) as T;
}

function writeStore<T>(key: string, value: T): T {
  window.localStorage.setItem(key, JSON.stringify(value));
  return value;
}

export async function getTemplates(): Promise<Template[]> {
  await wait();
  return readStore<Template[]>(STORAGE_KEYS.templates, seedTemplates);
}

export async function uploadTemplate(input: UploadTemplateInput): Promise<Template> {
  await wait(450);
  const templates = readStore<Template[]>(STORAGE_KEYS.templates, seedTemplates);
  const id = `template-${Date.now()}`;
  const template: Template = {
    id,
    name: input.name,
    category: input.category,
    tags: input.tags,
    format: input.format,
    status: 'draft',
    version: 1,
    createdAt: new Date().toISOString().slice(0, 10),
    variableCount: 5,
  };

  const detectedVariables: TemplateVariable[] = [
    'document_number',
    'document_date',
    'party_name',
    'amount',
    'include_appendix',
  ].map((name, index) => ({
    id: `var-${Date.now()}-${index}`,
    templateId: id,
    name,
    label: name,
    type: name.includes('date') ? 'date' : name.includes('amount') ? 'currency' : name.includes('include') ? 'boolean' : 'text',
    required: index < 4,
    defaultValue: '',
    hint: '',
  }));

  writeStore(STORAGE_KEYS.templates, [template, ...templates]);
  writeStore(STORAGE_KEYS.variables, [
    ...detectedVariables,
    ...readStore<TemplateVariable[]>(STORAGE_KEYS.variables, seedVariables),
  ]);

  return template;
}

export async function getTemplateVariables(templateId: string): Promise<TemplateVariable[]> {
  await wait();
  return readStore<TemplateVariable[]>(STORAGE_KEYS.variables, seedVariables).filter(
    (variable) => variable.templateId === templateId,
  );
}

export async function saveTemplateVariables(
  templateId: string,
  nextVariables: TemplateVariable[],
): Promise<TemplateVariable[]> {
  await wait(300);
  const variables = readStore<TemplateVariable[]>(STORAGE_KEYS.variables, seedVariables);
  const others = variables.filter((variable) => variable.templateId !== templateId);
  return writeStore(STORAGE_KEYS.variables, [...others, ...nextVariables]);
}

export async function getDocuments(): Promise<GeneratedDocument[]> {
  await wait();
  return readStore<GeneratedDocument[]>(STORAGE_KEYS.documents, seedDocuments);
}

export async function generateDocument(
  template: Template,
  values: Record<string, DocumentFieldValue>,
  format: 'docx' | 'pdf',
): Promise<GeneratedDocument> {
  await wait(500);
  const documents = readStore<GeneratedDocument[]>(STORAGE_KEYS.documents, seedDocuments);
  const number = String(values.contract_number || values.document_number || Date.now()).replace(/[\\/:*?"<>|]/g, '-');
  const document: GeneratedDocument = {
    id: `doc-${Date.now()}`,
    templateId: template.id,
    templateName: template.name,
    author: 'frontend.user',
    createdAt: new Date().toISOString(),
    format,
    fileName: `${template.name}_${number}.${format}`,
    values,
  };
  writeStore(STORAGE_KEYS.documents, [document, ...documents]);
  return document;
}
