import { FormEvent, useEffect, useMemo, useState } from 'react';
import {
  generateDocument,
  getDocuments,
  getTemplateVariables,
  getTemplates,
  saveTemplateVariables,
  uploadTemplate,
} from './api/mockApi';
import {
  DocumentFieldValue,
  GeneratedDocument,
  TableRowValue,
  Template,
  TemplateVariable,
  VariableType,
} from './types';
import { formatDate, formatDateTime, formatLabels, parseTags, statusLabels, variableTypeLabels } from './utils';

type Page = 'templates' | 'upload' | 'variables' | 'create' | 'history';

const emptyFilters = {
  search: '',
  category: 'all',
  status: 'all',
};

export function App() {
  const [page, setPage] = useState<Page>('templates');
  const [templates, setTemplates] = useState<Template[]>([]);
  const [documents, setDocuments] = useState<GeneratedDocument[]>([]);
  const [selectedTemplateId, setSelectedTemplateId] = useState<string>('');
  const [isLoading, setIsLoading] = useState(true);
  const [notice, setNotice] = useState('');

  const selectedTemplate = templates.find((template) => template.id === selectedTemplateId) || templates[0];

  const reload = async () => {
    setIsLoading(true);
    const [nextTemplates, nextDocuments] = await Promise.all([getTemplates(), getDocuments()]);
    setTemplates(nextTemplates);
    setDocuments(nextDocuments);
    setSelectedTemplateId((current) => current || nextTemplates[0]?.id || '');
    setIsLoading(false);
  };

  useEffect(() => {
    reload();
  }, []);

  const showNotice = (message: string) => {
    setNotice(message);
    window.setTimeout(() => setNotice(''), 3000);
  };

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand">
          <span className="brand-mark">TM</span>
          <div>
            <strong>Шаблонизатор</strong>
            <span>договоров</span>
          </div>
        </div>

        <nav className="nav">
          <button className={page === 'templates' ? 'active' : ''} onClick={() => setPage('templates')}>Шаблоны</button>
          <button className={page === 'upload' ? 'active' : ''} onClick={() => setPage('upload')}>Загрузка</button>
          <button className={page === 'variables' ? 'active' : ''} onClick={() => setPage('variables')}>Переменные</button>
          <button className={page === 'create' ? 'active' : ''} onClick={() => setPage('create')}>Создать документ</button>
          <button className={page === 'history' ? 'active' : ''} onClick={() => setPage('history')}>История</button>
        </nav>
      </aside>

      <main className="main">
        <header className="topbar">
          <div>
            <p className="eyebrow">React MVP на моковых данных</p>
            <h1>{getPageTitle(page)}</h1>
          </div>
          <div className="topbar-actions">
            <select value={selectedTemplateId} onChange={(event) => setSelectedTemplateId(event.target.value)}>
              {templates.map((template) => (
                <option key={template.id} value={template.id}>{template.name}</option>
              ))}
            </select>
            <button className="secondary" onClick={reload}>Обновить</button>
          </div>
        </header>

        {notice && <div className="notice">{notice}</div>}

        {isLoading ? (
          <LoadingState />
        ) : (
          <>
            {page === 'templates' && (
              <TemplatesPage
                templates={templates}
                onConfigure={(id) => {
                  setSelectedTemplateId(id);
                  setPage('variables');
                }}
                onCreate={(id) => {
                  setSelectedTemplateId(id);
                  setPage('create');
                }}
              />
            )}
            {page === 'upload' && (
              <UploadPage
                onUploaded={async (template) => {
                  await reload();
                  setSelectedTemplateId(template.id);
                  setPage('variables');
                  showNotice('Шаблон добавлен, переменные распознаны');
                }}
              />
            )}
            {page === 'variables' && selectedTemplate && (
              <VariablesPage template={selectedTemplate} onSaved={() => showNotice('Настройки переменных сохранены')} />
            )}
            {page === 'create' && selectedTemplate && (
              <CreateDocumentPage
                template={selectedTemplate}
                onGenerated={async () => {
                  await reload();
                  showNotice('Документ добавлен в историю');
                }}
              />
            )}
            {page === 'history' && (
              <HistoryPage
                documents={documents}
                onRepeat={(templateId) => {
                  setSelectedTemplateId(templateId);
                  setPage('create');
                }}
              />
            )}
          </>
        )}
      </main>
    </div>
  );
}

function getPageTitle(page: Page) {
  const titles: Record<Page, string> = {
    templates: 'Каталог шаблонов',
    upload: 'Загрузка шаблона',
    variables: 'Настройка переменных',
    create: 'Создание документа',
    history: 'История документов',
  };
  return titles[page];
}

function LoadingState() {
  return (
    <section className="state">
      <div className="spinner" />
      <p>Загрузка данных...</p>
    </section>
  );
}

function TemplatesPage({
  templates,
  onConfigure,
  onCreate,
}: {
  templates: Template[];
  onConfigure: (id: string) => void;
  onCreate: (id: string) => void;
}) {
  const [filters, setFilters] = useState(emptyFilters);
  const categories = Array.from(new Set(templates.map((template) => template.category)));

  const filteredTemplates = templates.filter((template) => {
    const bySearch = template.name.toLowerCase().includes(filters.search.toLowerCase());
    const byCategory = filters.category === 'all' || template.category === filters.category;
    const byStatus = filters.status === 'all' || template.status === filters.status;
    return bySearch && byCategory && byStatus;
  });

  return (
    <section className="content-stack">
      <div className="toolbar">
        <input
          placeholder="Поиск по названию"
          value={filters.search}
          onChange={(event) => setFilters({ ...filters, search: event.target.value })}
        />
        <select value={filters.category} onChange={(event) => setFilters({ ...filters, category: event.target.value })}>
          <option value="all">Все категории</option>
          {categories.map((category) => <option key={category}>{category}</option>)}
        </select>
        <select value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}>
          <option value="all">Все статусы</option>
          {Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
      </div>

      <div className="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Название</th>
              <th>Категория</th>
              <th>Формат</th>
              <th>Статус</th>
              <th>Версия</th>
              <th>Переменные</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {filteredTemplates.map((template) => (
              <tr key={template.id}>
                <td>
                  <strong>{template.name}</strong>
                  <span className="muted">{template.tags.join(', ')}</span>
                </td>
                <td>{template.category}</td>
                <td><span className="pill">{formatLabels[template.format]}</span></td>
                <td><span className={`status ${template.status}`}>{statusLabels[template.status]}</span></td>
                <td>v{template.version}</td>
                <td>{template.variableCount}</td>
                <td className="actions">
                  <button className="secondary" onClick={() => onConfigure(template.id)}>Настроить</button>
                  <button disabled={template.status !== 'published'} onClick={() => onCreate(template.id)}>Создать</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {filteredTemplates.length === 0 && <EmptyState text="Шаблоны по таким фильтрам не найдены" />}
    </section>
  );
}

function UploadPage({ onUploaded }: { onUploaded: (template: Template) => void }) {
  const [name, setName] = useState('');
  const [category, setCategory] = useState('Договоры');
  const [tags, setTags] = useState('');
  const [fileName, setFileName] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const canSubmit = name.trim() && fileName;

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (!canSubmit) return;
    setIsSubmitting(true);
    const format = fileName.toLowerCase().endsWith('.pdf') ? 'pdf' : 'docx';
    const template = await uploadTemplate({ name, category, tags: parseTags(tags), format });
    setIsSubmitting(false);
    onUploaded(template);
  };

  return (
    <form className="form-grid" onSubmit={submit}>
      <label>
        Название шаблона
        <input value={name} onChange={(event) => setName(event.target.value)} placeholder="Например: Договор аренды" />
      </label>
      <label>
        Категория
        <select value={category} onChange={(event) => setCategory(event.target.value)}>
          <option>Договоры</option>
          <option>Акты</option>
          <option>Соглашения</option>
        </select>
      </label>
      <label>
        Теги
        <input value={tags} onChange={(event) => setTags(event.target.value)} placeholder="через запятую" />
      </label>
      <label className="file-input">
        Файл шаблона
        <input
          type="file"
          accept=".docx,.pdf"
          onChange={(event) => setFileName(event.target.files?.[0]?.name || '')}
        />
        <span>{fileName || 'Выберите DOCX или PDF'}</span>
      </label>
      <div className="form-actions">
        <button disabled={!canSubmit || isSubmitting}>{isSubmitting ? 'Загрузка...' : 'Загрузить шаблон'}</button>
      </div>
    </form>
  );
}

function VariablesPage({ template, onSaved }: { template: Template; onSaved: () => void }) {
  const [variables, setVariables] = useState<TemplateVariable[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    setIsLoading(true);
    getTemplateVariables(template.id).then((items) => {
      setVariables(items);
      setIsLoading(false);
    });
  }, [template.id]);

  const updateVariable = (id: string, patch: Partial<TemplateVariable>) => {
    setVariables((current) => current.map((variable) => (variable.id === id ? { ...variable, ...patch } : variable)));
  };

  const save = async () => {
    setIsSaving(true);
    await saveTemplateVariables(template.id, variables);
    setIsSaving(false);
    onSaved();
  };

  if (isLoading) return <LoadingState />;

  return (
    <section className="content-stack">
      <div className="summary-band">
        <div>
          <span className="muted">Шаблон</span>
          <strong>{template.name}</strong>
        </div>
        <div>
          <span className="muted">Найдено переменных</span>
          <strong>{variables.length}</strong>
        </div>
      </div>

      <div className="variable-list">
        {variables.map((variable) => (
          <article className="variable-row" key={variable.id}>
            <div className="variable-code">{`{{${variable.name}}}`}</div>
            <label>
              Подпись
              <input value={variable.label} onChange={(event) => updateVariable(variable.id, { label: event.target.value })} />
            </label>
            <label>
              Тип
              <select
                value={variable.type}
                onChange={(event) => updateVariable(variable.id, { type: event.target.value as VariableType })}
              >
                {Object.entries(variableTypeLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
              </select>
            </label>
            <label>
              Значение по умолчанию
              <input
                value={variable.defaultValue}
                onChange={(event) => updateVariable(variable.id, { defaultValue: event.target.value })}
              />
            </label>
            <label>
              Подсказка
              <input value={variable.hint} onChange={(event) => updateVariable(variable.id, { hint: event.target.value })} />
            </label>
            <label className="checkbox-label">
              <input
                type="checkbox"
                checked={variable.required}
                onChange={(event) => updateVariable(variable.id, { required: event.target.checked })}
              />
              Обязательное
            </label>
          </article>
        ))}
      </div>

      <div className="form-actions">
        <button onClick={save} disabled={isSaving}>{isSaving ? 'Сохранение...' : 'Сохранить настройки'}</button>
      </div>
    </section>
  );
}

function CreateDocumentPage({ template, onGenerated }: { template: Template; onGenerated: () => void }) {
  const [variables, setVariables] = useState<TemplateVariable[]>([]);
  const [values, setValues] = useState<Record<string, DocumentFieldValue>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [format, setFormat] = useState<'docx' | 'pdf'>(template.format);
  const [preview, setPreview] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    getTemplateVariables(template.id).then((items) => {
      setVariables(items);
      setValues(
        Object.fromEntries(
          items.map((item) => [item.name, getInitialFieldValue(item)]),
        ),
      );
      setErrors({});
      setFormat(template.format);
      setPreview(false);
    });
  }, [template.id, template.format]);

  const validationErrors = () => {
    const nextErrors: Record<string, string> = {};
    variables.forEach((variable) => {
      const value = values[variable.name];
      if (
        variable.required
        && (value === ''
          || value === false
          || value === undefined
          || (Array.isArray(value) && value.length === 0))
      ) {
        nextErrors[variable.name] = 'Заполните обязательное поле';
      }
      if ((variable.type === 'number' || variable.type === 'currency') && value && Number.isNaN(Number(value))) {
        nextErrors[variable.name] = 'Введите число';
      }
    });
    return nextErrors;
  };

  const submit = async () => {
    const nextErrors = validationErrors();
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) return;
    setIsSubmitting(true);
    await generateDocument(template, values, format);
    setIsSubmitting(false);
    onGenerated();
  };

  return (
    <section className="split-view">
      <div className="content-stack">
        <div className="summary-band">
          <div>
            <span className="muted">Выбранный шаблон</span>
            <strong>{template.name}</strong>
          </div>
          <label>
            Формат результата
            <select value={format} onChange={(event) => setFormat(event.target.value as 'docx' | 'pdf')}>
              {template.format === 'docx' && <option value="docx">DOCX</option>}
              <option value="pdf">PDF</option>
            </select>
          </label>
        </div>

        <div className="form-grid single">
          {variables.map((variable) => (
            <DynamicField
              key={variable.id}
              variable={variable}
              value={values[variable.name]}
              error={errors[variable.name]}
              onChange={(value) => setValues((current) => ({ ...current, [variable.name]: value }))}
            />
          ))}
        </div>

        <div className="form-actions">
          <button className="secondary" onClick={() => setPreview((current) => !current)}>
            {preview ? 'Скрыть предпросмотр' : 'Показать предпросмотр'}
          </button>
          <button onClick={submit} disabled={isSubmitting}>{isSubmitting ? 'Генерация...' : 'Сгенерировать документ'}</button>
        </div>
      </div>

      <aside className="preview-panel">
        <h2>Предпросмотр данных</h2>
        {preview ? (
          <dl>
            {variables.map((variable) => (
              <div key={variable.id}>
                <dt>{variable.label}</dt>
                <dd>{formatPreviewValue(values[variable.name])}</dd>
              </div>
            ))}
          </dl>
        ) : (
          <EmptyState text="Откройте предпросмотр, чтобы проверить значения перед генерацией" />
        )}
      </aside>
    </section>
  );
}

function getInitialFieldValue(variable: TemplateVariable): DocumentFieldValue {
  if (variable.type === 'boolean') {
    return variable.defaultValue === 'true';
  }

  if (variable.type === 'table') {
    return [];
  }

  return variable.defaultValue;
}

function formatPreviewValue(value: DocumentFieldValue | undefined): string {
  if (Array.isArray(value)) {
    return value.length > 0 ? `${value.length} строк(и)` : 'Не заполнено';
  }

  if (value === true) return 'Да';
  if (value === false) return 'Нет';
  return value || 'Не заполнено';
}

function DynamicField({
  variable,
  value,
  error,
  onChange,
}: {
  variable: TemplateVariable;
  value: DocumentFieldValue | undefined;
  error?: string;
  onChange: (value: DocumentFieldValue) => void;
}) {
  const selectOptions = Array.isArray(variable.options) ? variable.options : [];

  return (
    <div className={error ? 'field has-error' : 'field'}>
      <span className="field-label">{variable.label}</span>
      {variable.type === 'textarea' && (
        <textarea value={String(value || '')} onChange={(event) => onChange(event.target.value)} />
      )}
      {variable.type === 'select' && (
        <select value={String(value || '')} onChange={(event) => onChange(event.target.value)}>
          <option value="">Выберите значение</option>
          {selectOptions.map((option) => <option key={option}>{option}</option>)}
        </select>
      )}
      {variable.type === 'boolean' && (
        <span className="inline-control">
          <input type="checkbox" checked={Boolean(value)} onChange={(event) => onChange(event.target.checked)} />
          Да
        </span>
      )}
      {variable.type === 'table' && (
        <TableField
          variable={variable}
          value={Array.isArray(value) ? value : []}
          onChange={onChange}
        />
      )}
      {!['textarea', 'select', 'boolean', 'table'].includes(variable.type) && (
        <input
          type={variable.type === 'date' ? 'date' : variable.type === 'number' || variable.type === 'currency' ? 'number' : 'text'}
          value={String(value || '')}
          onChange={(event) => onChange(event.target.value)}
        />
      )}
      {variable.hint && <span className="hint">{variable.hint}</span>}
      {error && <span className="error">{error}</span>}
    </div>
  );
}

function TableField({
  variable,
  value,
  onChange,
}: {
  variable: TemplateVariable;
  value: TableRowValue[];
  onChange: (value: TableRowValue[]) => void;
}) {
  const columns = !Array.isArray(variable.options) && variable.options?.columns?.length
    ? variable.options.columns
    : ['Наименование', 'Кол-во', 'Цена'];

  const addRow = () => {
    onChange([...value, Object.fromEntries(columns.map((column) => [column, '']))]);
  };

  const updateCell = (rowIndex: number, column: string, nextValue: string) => {
    onChange(
      value.map((row, index) => (index === rowIndex ? { ...row, [column]: nextValue } : row)),
    );
  };

  const removeRow = (rowIndex: number) => {
    onChange(value.filter((_, index) => index !== rowIndex));
  };

  return (
    <div className="table-field">
      {value.length > 0 && (
        <div className="table-field-head" style={{ gridTemplateColumns: `repeat(${columns.length}, minmax(120px, 1fr)) 90px` }}>
          {columns.map((column) => <span key={column}>{column}</span>)}
          <span />
        </div>
      )}
      {value.map((row, rowIndex) => (
        <div className="table-field-row" key={rowIndex} style={{ gridTemplateColumns: `repeat(${columns.length}, minmax(120px, 1fr)) 90px` }}>
          {columns.map((column) => (
            <input
              key={column}
              value={row[column] || ''}
              onChange={(event) => updateCell(rowIndex, column, event.target.value)}
              placeholder={column}
            />
          ))}
          <button className="secondary" type="button" onClick={() => removeRow(rowIndex)}>Удалить</button>
        </div>
      ))}
      <button className="secondary" type="button" onClick={addRow}>Добавить строку</button>
    </div>
  );
}

function HistoryPage({
  documents,
  onRepeat,
}: {
  documents: GeneratedDocument[];
  onRepeat: (templateId: string) => void;
}) {
  const [query, setQuery] = useState('');
  const filteredDocuments = useMemo(
    () => documents.filter((document) => document.templateName.toLowerCase().includes(query.toLowerCase())),
    [documents, query],
  );

  return (
    <section className="content-stack">
      <div className="toolbar">
        <input placeholder="Поиск по шаблону" value={query} onChange={(event) => setQuery(event.target.value)} />
      </div>
      <div className="document-grid">
        {filteredDocuments.map((document) => (
          <article className="document-card" key={document.id}>
            <div>
              <span className="pill">{document.format.toUpperCase()}</span>
              <h2>{document.fileName}</h2>
              <p>{document.templateName}</p>
            </div>
            <dl>
              <div>
                <dt>Автор</dt>
                <dd>{document.author}</dd>
              </div>
              <div>
                <dt>Создан</dt>
                <dd>{formatDateTime(document.createdAt)}</dd>
              </div>
            </dl>
            <div className="actions">
              <button className="secondary" onClick={() => onRepeat(document.templateId)}>Повторить</button>
              <button onClick={() => window.alert(`Моковое скачивание: ${document.fileName}`)}>Скачать</button>
            </div>
          </article>
        ))}
      </div>
      {filteredDocuments.length === 0 && <EmptyState text="Документы пока не созданы" />}
    </section>
  );
}

function EmptyState({ text }: { text: string }) {
  return (
    <div className="empty-state">
      <strong>Нет данных</strong>
      <span>{text}</span>
    </div>
  );
}
