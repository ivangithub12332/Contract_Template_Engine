import { TemplateFormat, TemplateStatus, UserRole, VariableType } from './types';

export const statusLabels: Record<TemplateStatus, string> = {
  draft: 'Черновик',
  published: 'Опубликован',
  archived: 'В архиве',
};

export const formatLabels: Record<TemplateFormat, string> = {
  docx: 'DOCX',
  pdf: 'PDF',
};

export const variableTypeLabels: Record<VariableType, string> = {
  text: 'Текст',
  textarea: 'Многострочный текст',
  number: 'Число',
  currency: 'Сумма',
  date: 'Дата',
  select: 'Выбор из списка',
  boolean: 'Логический признак',
  table: 'Повторяющийся блок / таблица',
};

export const roleLabels: Record<UserRole, string> = {
  admin: 'Администратор',
  methodologist: 'Шаблонизатор',
  user: 'Пользователь',
};

export function formatDate(value: string): string {
  return new Intl.DateTimeFormat('ru-RU').format(new Date(value));
}

export function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value));
}

export function parseTags(value: string): string[] {
  return value
    .split(',')
    .map((tag) => tag.trim())
    .filter(Boolean);
}
