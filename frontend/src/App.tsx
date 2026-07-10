import { FormEvent, useEffect, useMemo, useState } from 'react';
import {
  ApiError,
  deleteTemplate,
  downloadDocument,
  generateDocument,
  getCurrentUser,
  getDocument,
  getDocuments,
  getTemplateVariables,
  getTemplates,
  getUsers,
  hasAuthToken,
  login,
  logout,
  publishTemplate,
  register,
  saveTemplateVariables,
  updateUserRole,
  uploadTemplate,
  uploadTemplateVersion,
} from './api/backendApi';
import {
  AuthCredentials,
  DocumentFieldValue,
  GeneratedDocument,
  RegisterInput,
  TableRowValue,
  Template,
  TemplateVariable,
  User,
  UserRole,
  VariableType,
} from './types';
import { formatDate, formatDateTime, formatLabels, parseTags, roleLabels, statusLabels, variableTypeLabels } from './utils';

type Page = 'templates' | 'upload' | 'variables' | 'create' | 'history' | 'users';

const MAX_TEMPLATE_FILE_SIZE_BYTES = 50 * 1024 * 1024;
const TEMPLATE_FILE_SIZE_ERROR = 'Размер файла не должен превышать 50 МБ.';

function isTemplateFileSizeValid(file: File): boolean {
  return file.size <= MAX_TEMPLATE_FILE_SIZE_BYTES;
}

const emptyFilters = {
  search: '',
  category: 'all',
  status: 'all',
};

export function App() {
  const [user, setUser] = useState<User | null>(null);
  const [isAuthChecked, setIsAuthChecked] = useState(false);
  const [page, setPage] = useState<Page>('templates');
  const [templates, setTemplates] = useState<Template[]>([]);
  const [documents, setDocuments] = useState<GeneratedDocument[]>([]);
  const [selectedTemplateId, setSelectedTemplateId] = useState<string>('');
  const [prefillValues, setPrefillValues] = useState<Record<string, DocumentFieldValue> | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [refreshToken, setRefreshToken] = useState(0);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  const selectedTemplate = templates.find((template) => template.id === selectedTemplateId) || templates[0];
  const canManageTemplates = user?.role === 'admin' || user?.role === 'methodologist';
  const canManageUsers = user?.role === 'admin';

  const reload = async () => {
    setIsLoading(true);
    setError('');
    try {
      const [nextTemplates, nextDocuments] = await Promise.all([getTemplates(), getDocuments()]);
      setTemplates(nextTemplates);
      setDocuments(nextDocuments);
      setSelectedTemplateId((current) => (
        nextTemplates.some((template) => template.id === current) ? current : nextTemplates[0]?.id || ''
      ));
      setRefreshToken((current) => current + 1);
    } catch (requestError) {
      setError(getErrorMessage(requestError));
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (!hasAuthToken()) {
      setIsAuthChecked(true);
      return;
    }

    getCurrentUser()
      .then((currentUser) => {
        setUser(currentUser);
        return reload();
      })
      .catch((requestError) => {
        setError(getErrorMessage(requestError));
      })
      .finally(() => setIsAuthChecked(true));
  }, []);

  const showNotice = (message: string) => {
    setNotice(message);
    window.setTimeout(() => setNotice(''), 3000);
  };

  const handleAuth = async (action: 'login' | 'register', payload: AuthCredentials | RegisterInput) => {
    setError('');
    const currentUser = action === 'login'
      ? await login(payload as AuthCredentials)
      : await register(payload as RegisterInput);
    setUser(currentUser);
    await reload();
  };

  const handleLogout = async () => {
    await logout();
    setUser(null);
    setTemplates([]);
    setDocuments([]);
    setSelectedTemplateId('');
  };

  if (!isAuthChecked) {
    return <LoadingState />;
  }

  if (!user) {
    return <AuthPage error={error} onSubmit={handleAuth} />;
  }

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
          {canManageTemplates && (
            <>
              <button className={page === 'upload' ? 'active' : ''} onClick={() => setPage('upload')}>Загрузка</button>
              <button className={page === 'variables' ? 'active' : ''} onClick={() => setPage('variables')}>Переменные</button>
            </>
          )}
          <button
            className={page === 'create' ? 'active' : ''}
            onClick={() => {
              setPrefillValues(null);
              setPage('create');
            }}
          >
            Создать документ
          </button>
          <button className={page === 'history' ? 'active' : ''} onClick={() => setPage('history')}>История</button>
          {canManageUsers && (
            <button className={page === 'users' ? 'active' : ''} onClick={() => setPage('users')}>Пользователи</button>
          )}
        </nav>
      </aside>

      <main className="main">
        <header className="topbar">
          <div>
            <p className="eyebrow">API: Laravel / Sanctum</p>
            <h1>{getPageTitle(page)}</h1>
          </div>
          <div className="topbar-actions">
            <select
              value={selectedTemplateId}
              disabled={templates.length === 0}
              onChange={(event) => {
                setSelectedTemplateId(event.target.value);
                setPrefillValues(null);
              }}
            >
              {templates.length === 0
                ? <option value="">Нет шаблонов</option>
                : templates.map((template) => (
                  <option key={template.id} value={template.id}>{template.name}</option>
                ))}
            </select>
            <span className="user-badge">{user.name} / {user.role}</span>
            <button className="secondary" onClick={reload} disabled={isLoading}>
              {isLoading ? 'Обновление...' : 'Обновить'}
            </button>
            <button className="secondary" onClick={handleLogout}>Выйти</button>
          </div>
        </header>

        {notice && <div className="notice">{notice}</div>}
        {error && <div className="error-banner">{error}</div>}

        {isLoading ? (
          <LoadingState />
        ) : (
          <>
            {page === 'templates' && (
              <TemplatesPage
                templates={templates}
                onConfigure={(id) => {
                  setSelectedTemplateId(id);
                  setPrefillValues(null);
                  setPage('variables');
                }}
                onCreate={(id) => {
                  setSelectedTemplateId(id);
                  setPrefillValues(null);
                  setPage('create');
                }}
                onDelete={async (id) => {
                  await deleteTemplate(id);
                  setPrefillValues(null);
                  await reload();
                  showNotice('Шаблон удалён');
                }}
                canManageTemplates={canManageTemplates}
                canDeleteTemplates={canManageUsers}
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
            {page === 'variables' && (
              selectedTemplate ? (
                <VariablesPage
                  template={selectedTemplate}
                  refreshToken={refreshToken}
                  onSaved={async (message = 'Настройки переменных сохранены') => {
                    showNotice(message);
                    await reload();
                  }}
                />
              ) : (
                <section className="content-stack">
                  <EmptyState text="Сначала загрузите шаблон, чтобы настроить переменные" />
                </section>
              )
            )}
            {page === 'create' && (
              selectedTemplate ? (
                <CreateDocumentPage
                  template={selectedTemplate}
                  initialValues={prefillValues}
                  refreshToken={refreshToken}
                  onGenerated={async () => {
                    setPrefillValues(null);
                    await reload();
                    showNotice('Документ добавлен в историю');
                  }}
                />
              ) : (
                <section className="content-stack">
                  <EmptyState text="Нет доступных шаблонов для создания документа" />
                </section>
              )
            )}
            {page === 'history' && (
              <HistoryPage
                documents={documents}
                onRepeat={async (document) => {
                  const detailedDocument = await getDocument(document.id);
                  setSelectedTemplateId(detailedDocument.templateId || document.templateId);
                  setPrefillValues(detailedDocument.values);
                  setPage('create');
                }}
              />
            )}
            {page === 'users' && canManageUsers && (
              <UsersPage currentUser={user} refreshToken={refreshToken} onCurrentUserUpdated={setUser} />
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
    users: 'Пользователи и роли',
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

function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors)[0]?.[0] : '';
    return firstFieldError || error.message;
  }

  if (error instanceof Error) {
    return error.message;
  }

  return 'Неизвестная ошибка';
}

function AuthPage({
  error,
  onSubmit,
}: {
  error: string;
  onSubmit: (action: 'login' | 'register', payload: AuthCredentials | RegisterInput) => Promise<void>;
}) {
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [localError, setLocalError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setLocalError('');
    setIsSubmitting(true);
    try {
      await onSubmit(mode, mode === 'login' ? { email, password } : { name, email, password });
    } catch (requestError) {
      setLocalError(getErrorMessage(requestError));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <main className="auth-screen">
      <form className="auth-card" onSubmit={submit}>
        <div>
          <p className="eyebrow">Шаблонизатор договоров</p>
          <h1>{mode === 'login' ? 'Вход' : 'Регистрация'}</h1>
        </div>
        {mode === 'register' && (
          <label>
            Имя
            <input value={name} onChange={(event) => setName(event.target.value)} required />
          </label>
        )}
        <label>
          Email
          <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
        </label>
        <label>
          Пароль
          <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} required />
        </label>
        {(localError || error) && <div className="error-banner">{localError || error}</div>}
        <div className="form-actions">
          <button type="button" className="secondary" onClick={() => setMode(mode === 'login' ? 'register' : 'login')}>
            {mode === 'login' ? 'Создать аккаунт' : 'У меня есть аккаунт'}
          </button>
          <button disabled={isSubmitting}>{isSubmitting ? 'Отправка...' : mode === 'login' ? 'Войти' : 'Зарегистрироваться'}</button>
        </div>
      </form>
    </main>
  );
}

function TemplatesPage({
  templates,
  onConfigure,
  onCreate,
  onDelete,
  canManageTemplates,
  canDeleteTemplates,
}: {
  templates: Template[];
  onConfigure: (id: string) => void;
  onCreate: (id: string) => void;
  onDelete: (id: string) => Promise<void>;
  canManageTemplates: boolean;
  canDeleteTemplates: boolean;
}) {
  const [filters, setFilters] = useState(emptyFilters);
  const [deletingId, setDeletingId] = useState('');
  const [error, setError] = useState('');
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
      {error && <div className="error-banner">{error}</div>}

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
                  {canManageTemplates && (
                    <button className="secondary" onClick={() => onConfigure(template.id)}>Настроить</button>
                  )}
                  <button disabled={template.status !== 'published'} onClick={() => onCreate(template.id)}>Создать</button>
                  {canDeleteTemplates && (
                    <button
                      className="danger"
                      disabled={deletingId === template.id}
                      onClick={async () => {
                        const confirmed = window.confirm(
                          `Удалить шаблон "${template.name}"? Связанные версии и документы тоже будут удалены.`,
                        );
                        if (!confirmed) return;

                        setDeletingId(template.id);
                        setError('');
                        try {
                          await onDelete(template.id);
                        } catch (requestError) {
                          setError(getErrorMessage(requestError));
                        } finally {
                          setDeletingId('');
                        }
                      }}
                    >
                      {deletingId === template.id ? 'Удаление...' : 'Удалить'}
                    </button>
                  )}
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

function UploadPage({ onUploaded }: { onUploaded: (template: Template) => void | Promise<void> }) {
  const [name, setName] = useState('');
  const [category, setCategory] = useState('Договоры');
  const [tags, setTags] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const canSubmit = name.trim() && file;

  const selectFile = (selectedFile: File | null) => {
    if (selectedFile && !isTemplateFileSizeValid(selectedFile)) {
      setFile(null);
      setError(TEMPLATE_FILE_SIZE_ERROR);
      return;
    }

    setFile(selectedFile);
    setError('');
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (!canSubmit || !file) return;
    setIsSubmitting(true);
    setError('');
    try {
      const format = file.name.toLowerCase().endsWith('.pdf') ? 'pdf' : 'docx';
      const template = await uploadTemplate({ name, category, tags: parseTags(tags), format, file });
      await onUploaded(template);
    } catch (requestError) {
      setError(getErrorMessage(requestError));
    } finally {
      setIsSubmitting(false);
    }
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
          onChange={(event) => selectFile(event.target.files?.[0] || null)}
        />
        <span>{file?.name || 'Выберите DOCX или PDF'}</span>
      </label>
      {error && <div className="error-banner">{error}</div>}
      <div className="form-actions">
        <button disabled={!canSubmit || isSubmitting}>{isSubmitting ? 'Загрузка...' : 'Загрузить шаблон'}</button>
      </div>
    </form>
  );
}

function VariablesPage({
  template,
  refreshToken,
  onSaved,
}: {
  template: Template;
  refreshToken: number;
  onSaved: (message?: string) => void | Promise<void>;
}) {
  const [variables, setVariables] = useState<TemplateVariable[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [isPublishing, setIsPublishing] = useState(false);
  const [versionFile, setVersionFile] = useState<File | null>(null);
  const [isUploadingVersion, setIsUploadingVersion] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    setIsLoading(true);
    setError('');
    getTemplateVariables(template.id)
      .then((items) => {
        setVariables(items);
      })
      .catch((requestError) => setError(getErrorMessage(requestError)))
      .finally(() => setIsLoading(false));
  }, [template.id, refreshToken]);

  const updateVariable = (id: string, patch: Partial<TemplateVariable>) => {
    setVariables((current) => current.map((variable) => (variable.id === id ? { ...variable, ...patch } : variable)));
  };

  const save = async () => {
    setIsSaving(true);
    setError('');
    try {
      await saveTemplateVariables(template.id, variables);
      await onSaved();
    } catch (requestError) {
      setError(getErrorMessage(requestError));
    } finally {
      setIsSaving(false);
    }
  };

  const publish = async () => {
    setIsPublishing(true);
    setError('');
    try {
      await publishTemplate(template.id);
      await onSaved('Шаблон опубликован');
    } catch (requestError) {
      setError(getErrorMessage(requestError));
    } finally {
      setIsPublishing(false);
    }
  };

  const uploadVersion = async () => {
    if (!versionFile) return;
    setIsUploadingVersion(true);
    setError('');
    try {
      await uploadTemplateVersion(template.id, versionFile);
      const items = await getTemplateVariables(template.id);
      setVariables(items);
      setVersionFile(null);
      await onSaved('Новая версия шаблона загружена, переменные обновлены');
    } catch (requestError) {
      setError(getErrorMessage(requestError));
    } finally {
      setIsUploadingVersion(false);
    }
  };

  const selectVersionFile = (selectedFile: File | null) => {
    if (selectedFile && !isTemplateFileSizeValid(selectedFile)) {
      setVersionFile(null);
      setError(TEMPLATE_FILE_SIZE_ERROR);
      return;
    }

    setVersionFile(selectedFile);
    setError('');
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
      <div className="version-upload">
        <label className="file-input">
          Новая версия шаблона
          <input
            type="file"
            accept=".docx,.pdf"
            onChange={(event) => selectVersionFile(event.target.files?.[0] || null)}
          />
          <span>{versionFile?.name || 'Выберите DOCX или PDF'}</span>
        </label>
        <button className="secondary" onClick={uploadVersion} disabled={!versionFile || isUploadingVersion}>
          {isUploadingVersion ? 'Загрузка...' : 'Загрузить версию'}
        </button>
      </div>
      {error && <div className="error-banner">{error}</div>}

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
        <button className="secondary" onClick={publish} disabled={isPublishing || template.status === 'published'}>
          {template.status === 'published' ? 'Уже опубликован' : isPublishing ? 'Публикация...' : 'Опубликовать'}
        </button>
      </div>
    </section>
  );
}

function CreateDocumentPage({
  template,
  initialValues,
  refreshToken,
  onGenerated,
}: {
  template: Template;
  initialValues: Record<string, DocumentFieldValue> | null;
  refreshToken: number;
  onGenerated: () => void | Promise<void>;
}) {
  const [variables, setVariables] = useState<TemplateVariable[]>([]);
  const [values, setValues] = useState<Record<string, DocumentFieldValue>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [apiError, setApiError] = useState('');
  const [format, setFormat] = useState<'docx' | 'pdf'>(template.format);
  const [preview, setPreview] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    setApiError('');
    setFormat(template.format);
    setPreview(false);

    if (template.status !== 'published') {
      setVariables([]);
      setValues({});
      setErrors({});
      return;
    }

    getTemplateVariables(template.id)
      .then((items) => {
        setVariables(items);
        const defaultValues = Object.fromEntries(
          items.map((item) => [item.name, getInitialFieldValue(item)]),
        );
        setValues((currentValues) => ({ ...defaultValues, ...currentValues, ...(initialValues || {}) }));
        setErrors({});
      })
      .catch((requestError) => setApiError(getErrorMessage(requestError)));
  }, [template.id, template.format, template.status, initialValues, refreshToken]);

  if (template.status !== 'published') {
    return (
      <section className="content-stack">
        <div className="summary-band">
          <div>
            <span className="muted">Выбранный шаблон</span>
            <strong>{template.name}</strong>
          </div>
          <div>
            <span className="muted">Статус</span>
            <strong>{statusLabels[template.status]}</strong>
          </div>
        </div>
        <EmptyState text="Документ можно создать только по опубликованному шаблону. Откройте переменные, настройте поля и нажмите 'Опубликовать'." />
      </section>
    );
  }

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
    setApiError('');
    try {
      const generatedDocument = await generateDocument(template, values);
      await downloadDocument(generatedDocument, format);
      await onGenerated();
    } catch (requestError) {
      setApiError(getErrorMessage(requestError));
    } finally {
      setIsSubmitting(false);
    }
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
        {apiError && <div className="error-banner">{apiError}</div>}

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
          {variables.length === 0 && (
            <div className="form-empty">
              <EmptyState text="У шаблона нет распознанных переменных. Проверьте разметку шаблона или запустите распознавание после загрузки." />
            </div>
          )}
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
  onRepeat: (document: GeneratedDocument) => void | Promise<void>;
}) {
  const [query, setQuery] = useState('');
  const [error, setError] = useState('');
  const [repeatingId, setRepeatingId] = useState('');
  const filteredDocuments = useMemo(
    () => documents.filter((document) => document.templateName.toLowerCase().includes(query.toLowerCase())),
    [documents, query],
  );

  const download = async (document: GeneratedDocument, format?: 'docx' | 'pdf') => {
    setError('');
    try {
      await downloadDocument(document, format);
    } catch (requestError) {
      setError(getErrorMessage(requestError));
    }
  };

  return (
    <section className="content-stack">
      <div className="toolbar">
        <input placeholder="Поиск по шаблону" value={query} onChange={(event) => setQuery(event.target.value)} />
      </div>
      {error && <div className="error-banner">{error}</div>}
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
              <button
                className="secondary"
                disabled={repeatingId === document.id}
                onClick={async () => {
                  setRepeatingId(document.id);
                  setError('');
                  try {
                    await onRepeat(document);
                  } catch (requestError) {
                    setError(getErrorMessage(requestError));
                  } finally {
                    setRepeatingId('');
                  }
                }}
              >
                {repeatingId === document.id ? 'Открытие...' : 'Повторить'}
              </button>
              <button onClick={() => download(document)}>Скачать</button>
              {document.format === 'docx' && (
                <button className="secondary" onClick={() => download(document, 'pdf')}>PDF</button>
              )}
            </div>
          </article>
        ))}
      </div>
      {filteredDocuments.length === 0 && <EmptyState text="Документы пока не созданы" />}
    </section>
  );
}

function UsersPage({
  currentUser,
  refreshToken,
  onCurrentUserUpdated,
}: {
  currentUser: User;
  refreshToken: number;
  onCurrentUserUpdated: (user: User) => void;
}) {
  const [users, setUsers] = useState<User[]>([]);
  const [query, setQuery] = useState('');
  const [roleFilter, setRoleFilter] = useState<UserRole | 'all'>('all');
  const [isLoading, setIsLoading] = useState(true);
  const [savingId, setSavingId] = useState<number | null>(null);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const loadUsers = async () => {
    setIsLoading(true);
    setError('');
    try {
      setUsers(await getUsers());
    } catch (requestError) {
      setError(getErrorMessage(requestError));
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadUsers();
  }, [refreshToken]);

  const filteredUsers = users.filter((user) => {
    const matchesQuery = [user.name, user.email].some((value) => value.toLowerCase().includes(query.toLowerCase()));
    const matchesRole = roleFilter === 'all' || user.role === roleFilter;
    return matchesQuery && matchesRole;
  });

  const changeRole = async (targetUser: User, role: UserRole) => {
    setSavingId(targetUser.id);
    setError('');
    setNotice('');
    try {
      const updatedUser = await updateUserRole(targetUser.id, role);
      setUsers((current) => current.map((user) => (user.id === updatedUser.id ? updatedUser : user)));
      if (updatedUser.id === currentUser.id) {
        onCurrentUserUpdated(updatedUser);
      }
      setNotice('Роль пользователя обновлена');
    } catch (requestError) {
      setError(getErrorMessage(requestError));
    } finally {
      setSavingId(null);
    }
  };

  if (isLoading) return <LoadingState />;

  return (
    <section className="content-stack">
      <div className="toolbar">
        <input
          placeholder="Поиск по имени или email"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
        />
        <select value={roleFilter} onChange={(event) => setRoleFilter(event.target.value as UserRole | 'all')}>
          <option value="all">Все роли</option>
          {Object.entries(roleLabels).map(([value, label]) => (
            <option key={value} value={value}>{label}</option>
          ))}
        </select>
        <button className="secondary" onClick={loadUsers}>Обновить</button>
      </div>

      {notice && <div className="notice">{notice}</div>}
      {error && <div className="error-banner">{error}</div>}

      <div className="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Имя</th>
              <th>Email</th>
              <th>Роль</th>
              <th>Создан</th>
            </tr>
          </thead>
          <tbody>
            {filteredUsers.map((user) => (
              <tr key={user.id}>
                <td>
                  <strong>{user.name}</strong>
                  {user.id === currentUser.id && <span className="muted">текущая учётная запись</span>}
                </td>
                <td>{user.email}</td>
                <td>
                  <select
                    value={user.role}
                    disabled={savingId === user.id || user.id === currentUser.id}
                    onChange={(event) => changeRole(user, event.target.value as UserRole)}
                  >
                    {Object.entries(roleLabels).map(([value, label]) => (
                      <option key={value} value={value}>{label}</option>
                    ))}
                  </select>
                </td>
                <td>{user.createdAt ? formatDate(user.createdAt) : '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {filteredUsers.length === 0 && <EmptyState text="Пользователи по таким фильтрам не найдены" />}
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
