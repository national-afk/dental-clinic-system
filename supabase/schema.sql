-- ============================================================
-- DENTAL CLINIC SYSTEM — Supabase schema
-- Run this in: Supabase Dashboard -> SQL Editor -> New query
-- ============================================================

create extension if not exists "pgcrypto";

-- ---------------------------------------------------------
-- STAFF / USERS (simple login for the PHP admin panel)
-- ---------------------------------------------------------
create table if not exists staff (
  id uuid primary key default gen_random_uuid(),
  staff_code text unique,                  -- e.g. EMP-00001 (generated in PHP)
  full_name text not null,
  username text unique not null,
  email text,
  password_hash text not null,
  role text not null default 'cashier',   -- superior | manager | doctor | nurse | cashier
  department text,
  phone text,
  gender text,
  date_of_birth date,
  address text,
  photo_url text,
  employment_status text not null default 'active', -- active | on_leave | terminated
  date_joined date not null default current_date,
  specialization text,                     -- Doctor
  license_number text,                     -- Doctor / Nurse
  license_expiry date,
  national_id text,
  nationality text,
  marital_status text,
  blood_group text,
  qualification text,
  emergency_contact_name text,
  emergency_contact_phone text,
  emergency_contact_relation text,
  active boolean not null default true,
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------
-- VITAL SIGNS (recorded by nurses)
-- ---------------------------------------------------------
create table if not exists vital_signs (
  id uuid primary key default gen_random_uuid(),
  patient_id uuid references patients(id) on delete cascade,
  recorded_by uuid references staff(id),
  blood_pressure text,
  temperature numeric(5,2),
  pulse int,
  weight numeric(6,2),
  height numeric(6,2),
  notes text,
  recorded_at timestamptz not null default now()
);

-- ---------------------------------------------------------
-- PATIENT NOTES (any clinical staff can add a timestamped note)
-- ---------------------------------------------------------
create table if not exists patient_notes (
  id uuid primary key default gen_random_uuid(),
  patient_id uuid references patients(id) on delete cascade,
  staff_id uuid references staff(id),
  note text not null,
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------
-- AUDIT LOGS (who did what, when)
-- ---------------------------------------------------------
create table if not exists audit_logs (
  id uuid primary key default gen_random_uuid(),
  staff_id uuid references staff(id),
  action text not null,                    -- e.g. 'login', 'staff.create', 'patient.create'
  details text,
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------
-- PATIENTS
-- ---------------------------------------------------------
create table if not exists patients (
  id uuid primary key default gen_random_uuid(),
  patient_code text unique not null,        -- e.g. PT-000123 (generated in PHP)
  full_name text not null,
  phone text,
  email text,
  age int,
  gender text,                              -- Male | Female
  address text,
  medical_history text,                     -- Taariikhda caafimaadka
  allergies text,
  dental_history text,
  doctor_notes text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- Patient documents / x-rays (metadata; files themselves can live in Supabase Storage)
create table if not exists patient_documents (
  id uuid primary key default gen_random_uuid(),
  patient_id uuid references patients(id) on delete cascade,
  file_name text not null,
  file_url text not null,                   -- Supabase Storage public/signed URL
  doc_type text default 'xray',              -- xray | document | photo
  uploaded_at timestamptz not null default now()
);

-- ---------------------------------------------------------
-- DENTAL CHART (Teeth 1-32, Universal Numbering System)
-- ---------------------------------------------------------
create table if not exists dental_chart (
  id uuid primary key default gen_random_uuid(),
  patient_id uuid references patients(id) on delete cascade,
  tooth_number int not null check (tooth_number between 1 and 32),
  condition text not null default 'healthy',
  -- healthy | cavity | filling | extraction | crown | root_canal | missing
  treatment_status text not null default 'none',
  -- none | planned | in_progress | completed
  notes text,                                -- before/after notes
  updated_at timestamptz not null default now(),
  unique (patient_id, tooth_number)
);

-- ---------------------------------------------------------
-- SERVICES / TREATMENT PRICE LIST
-- ---------------------------------------------------------
create table if not exists services (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  category text,
  default_price numeric(10,2) not null default 0,
  active boolean not null default true
);

insert into services (name, category, default_price) values
  ('Consultation','General',10),
  ('Cleaning (Scaling)','General',25),
  ('Filling','Restorative',30),
  ('Extraction','Surgical',20),
  ('Root Canal Treatment','Restorative',80),
  ('Crown & Bridge','Restorative',150),
  ('Teeth Whitening','Cosmetic',60),
  ('X-Ray','Diagnostic',15),
  ('Orthodontics (Braces)','Orthodontics',400),
  ('Implants','Surgical',500)
on conflict do nothing;

-- ---------------------------------------------------------
-- APPOINTMENTS
-- ---------------------------------------------------------
create table if not exists appointments (
  id uuid primary key default gen_random_uuid(),
  patient_id uuid references patients(id) on delete cascade,
  doctor_id uuid references staff(id),
  service_id uuid references services(id),
  appointment_date date not null,
  appointment_time time not null,
  status text not null default 'booked',
  -- booked | confirmed | completed | cancelled | rescheduled
  reminder_sent boolean not null default false,
  notes text,
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------
-- TREATMENTS PERFORMED (links patient + service + tooth)
-- ---------------------------------------------------------
create table if not exists treatments (
  id uuid primary key default gen_random_uuid(),
  patient_id uuid references patients(id) on delete cascade,
  appointment_id uuid references appointments(id),
  service_id uuid references services(id),
  doctor_id uuid references staff(id),
  tooth_number int,
  price numeric(10,2) not null default 0,
  status text not null default 'in_progress',
  -- planned | in_progress | completed
  treatment_date date not null default current_date,
  notes text,
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------
-- BILLING / INVOICES
-- ---------------------------------------------------------
create table if not exists invoices (
  id uuid primary key default gen_random_uuid(),
  invoice_no text unique not null,
  patient_id uuid references patients(id) on delete cascade,
  treatment_id uuid references treatments(id),
  total_amount numeric(10,2) not null default 0,
  discount numeric(10,2) not null default 0,
  paid_amount numeric(10,2) not null default 0,
  balance numeric(10,2) generated always as (total_amount - discount - paid_amount) stored,
  status text not null default 'unpaid',   -- unpaid | partial | paid
  created_at timestamptz not null default now()
);

create table if not exists payments (
  id uuid primary key default gen_random_uuid(),
  invoice_id uuid references invoices(id) on delete cascade,
  amount numeric(10,2) not null,
  method text not null default 'cash',      -- cash | card | mobile_money
  notes text,                               -- e.g. refund reason
  paid_at timestamptz not null default now(),
  received_by uuid references staff(id)
);

-- ---------------------------------------------------------
-- PHARMACY / INVENTORY
-- ---------------------------------------------------------
create table if not exists inventory_items (
  id uuid primary key default gen_random_uuid(),
  item_name text not null,
  item_type text not null default 'material', -- material | medicine
  category text,                              -- e.g. Antibiotic, Painkiller, Anesthetic
  unit text default 'pcs',
  quantity numeric(10,2) not null default 0,
  low_stock_threshold numeric(10,2) not null default 5,
  unit_price numeric(10,2) default 0,
  expiry_date date,
  created_at timestamptz not null default now()
);

create table if not exists inventory_movements (
  id uuid primary key default gen_random_uuid(),
  item_id uuid references inventory_items(id) on delete cascade,
  movement_type text not null,              -- in | out
  quantity numeric(10,2) not null,
  reason text,
  moved_by uuid references staff(id),
  moved_at timestamptz not null default now()
);

-- ---------------------------------------------------------
-- EXPENSES (Manager only)
-- ---------------------------------------------------------
create table if not exists expenses (
  id uuid primary key default gen_random_uuid(),
  category text not null,
  amount numeric(10,2) not null default 0,
  expense_date date not null default current_date,
  notes text,
  recorded_by uuid references staff(id),
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------
-- CASHIER SHIFTS — must "open" a shift before making POS sales
-- ---------------------------------------------------------
create table if not exists cashier_shifts (
  id uuid primary key default gen_random_uuid(),
  cashier_id uuid references staff(id),
  shift_type text default 'custom',     -- morning | evening | custom
  opened_at timestamptz not null default now(),
  closed_at timestamptz,
  opening_cash numeric(10,2) not null default 0,
  closing_cash numeric(10,2),
  status text not null default 'open', -- open | closed
  notes text
);

-- ---------------------------------------------------------
-- LAB ORDERS (Lab Technician role)
-- ---------------------------------------------------------
create table if not exists lab_orders (
  id uuid primary key default gen_random_uuid(),
  patient_id uuid references patients(id) on delete cascade,
  treatment_id uuid references treatments(id),
  order_type text not null,                  -- crown | bridge | denture | other
  status text not null default 'ordered',    -- ordered | in_progress | ready | delivered
  order_date date not null default current_date,
  expected_delivery_date date,
  delivered_date date,
  notes text,
  created_by uuid references staff(id),
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------
-- POS (Point of Sale) — Cashier sells pharmacy items directly
-- ---------------------------------------------------------
create table if not exists pos_sales (
  id uuid primary key default gen_random_uuid(),
  sale_no text not null unique,
  patient_id uuid references patients(id),  -- optional: null = walk-in customer
  total_amount numeric(10,2) not null default 0,
  payment_method text not null default 'cash',
  cashier_id uuid references staff(id),
  shift_id uuid references cashier_shifts(id),
  created_at timestamptz not null default now()
);

create table if not exists pos_sale_items (
  id uuid primary key default gen_random_uuid(),
  sale_id uuid references pos_sales(id) on delete cascade,
  item_id uuid references inventory_items(id),
  item_name text not null,
  quantity numeric(10,2) not null,
  unit_price numeric(10,2) not null,
  subtotal numeric(10,2) not null
);

-- ---------------------------------------------------------
-- CLINIC SETTINGS (single row, Manager-editable)
-- ---------------------------------------------------------
create table if not exists clinic_settings (
  id int primary key default 1,
  clinic_name text not null default 'Future Dental Clinic',
  currency_symbol text not null default '$',
  currency_code text default 'USD',
  secondary_currency_name text,
  secondary_currency_code text,
  exchange_rate numeric(14,4),
  logo_url text,
  clinic_address text,
  clinic_phone text,
  receipt_footer text default 'Thank you for choosing us!',
  receipt_auto_print boolean not null default true,
  receipt_paper text not null default '80mm',   -- 58mm | 80mm | a4
  updated_at timestamptz not null default now(),
  constraint single_row check (id = 1)
);
insert into clinic_settings (id) values (1) on conflict (id) do nothing;

-- ---------------------------------------------------------
-- SEQUENTIAL ID COUNTERS (Patient ID, Invoice No, POS Sale No -> 00001, 00002, ...)
-- ---------------------------------------------------------
create table if not exists counters (
  id text primary key,
  next_value bigint not null default 1
);

insert into counters (id, next_value) values
  ('patient', 1),
  ('invoice', 1),
  ('pos_sale', 1)
on conflict (id) do nothing;

create or replace function get_next_number(counter_id text)
returns bigint
language plpgsql
as $$
declare
  result bigint;
begin
  update counters
  set next_value = next_value + 1
  where id = counter_id
  returning next_value - 1 into result;

  if result is null then
    insert into counters (id, next_value) values (counter_id, 2);
    result := 1;
  end if;

  return result;
end;
$$;

grant execute on function get_next_number(text) to anon;

-- ---------------------------------------------------------
-- DOCTOR ATTENDANCE (check-in / check-out)
-- ---------------------------------------------------------
create table if not exists doctor_attendance (
  id uuid primary key default gen_random_uuid(),
  doctor_id uuid references staff(id),
  check_in timestamptz not null default now(),
  check_out timestamptz,
  shift_type text,                   -- morning | evening
  notes text,
  status text not null default 'in' -- in | out
);

-- ---------------------------------------------------------
-- PRESCRIPTIONS (written by doctors for patients)
-- ---------------------------------------------------------
create table if not exists prescriptions (
  id uuid primary key default gen_random_uuid(),
  patient_id uuid references patients(id),
  doctor_id uuid references staff(id),
  medicine_name text not null,
  dosage text,
  instructions text,
  prescribed_date date not null default current_date,
  created_at timestamptz not null default now()
);

-- ---------------------------------------------------------
-- BRACES TRACKING
-- ---------------------------------------------------------
create table if not exists braces_cases (
  id uuid primary key default gen_random_uuid(),
  patient_id uuid references patients(id) on delete cascade,
  doctor_id uuid references staff(id),
  start_date date not null,
  interval_days int not null default 30,
  next_visit_date date not null,
  status text not null default 'active',   -- active | completed | removed
  notes text,
  created_at timestamptz not null default now()
);

create table if not exists braces_visits (
  id uuid primary key default gen_random_uuid(),
  braces_case_id uuid references braces_cases(id) on delete cascade,
  visit_type text not null,        -- started | adjustment_due | adjustment_completed
  visit_date date not null,
  notes text,
  recorded_by uuid references staff(id),
  created_at timestamptz not null default now()
);

-- ============================================================
-- ROW LEVEL SECURITY
-- This system is called ONLY from the PHP backend (server-side)
-- using the anon/publishable key over a private network path,
-- never exposed to the browser. We enable RLS and allow the
-- anon role to read/write, since PHP session login is the real
-- access gate. For a public-facing app, replace these with real
-- per-user policies and use the service_role key server-side.
-- ============================================================
do $$
declare t text;
begin
  for t in select tablename from pg_tables where schemaname = 'public'
  loop
    execute format('alter table public.%I enable row level security;', t);
    execute format('drop policy if exists anon_all on public.%I;', t);
    execute format('create policy anon_all on public.%I for all to anon using (true) with check (true);', t);
  end loop;
end $$;

-- NOTE: no admin user is seeded here on purpose, because a real login
-- needs a proper PHP password_hash(), not a fake one written in SQL.
-- After running this schema, run setup/create_admin.php once from the
-- command line (php setup/create_admin.php) to create your first user.

-- ---------------------------------------------------------
-- APP SETTINGS (key/value, used by the Settings hub)
-- ---------------------------------------------------------
create table if not exists app_settings (
  key text primary key,
  value text,
  updated_at timestamptz not null default now()
);
alter table app_settings enable row level security;
drop policy if exists anon_all on app_settings;
create policy anon_all on app_settings for all to anon using (true) with check (true);

-- ---------------------------------------------------------
-- NOTIFICATION LOG (SMS / WhatsApp / Email reminders)
-- ---------------------------------------------------------
create table if not exists notification_log (
  id uuid primary key default gen_random_uuid(),
  kind text not null,
  entity_id uuid,
  for_date date,
  patient_id uuid references patients(id) on delete set null,
  channel text not null,
  to_address text,
  message text,
  status text not null,
  error text,
  created_at timestamptz not null default now()
);
alter table notification_log enable row level security;
drop policy if exists anon_all on notification_log;
create policy anon_all on notification_log for all to anon using (true) with check (true);

-- ---------------------------------------------------------
-- DAILY ACTIVITIES REPORT (per staff member, per day)
-- ---------------------------------------------------------
create table if not exists daily_reports (
  id uuid primary key default gen_random_uuid(),
  report_date date not null,
  staff_id uuid references staff(id) on delete cascade,
  patients_attended int not null default 0,
  appointments_scheduled int not null default 0,
  followups_contacted int not null default 0,
  missed_appointments int not null default 0,
  treatments_assisted int not null default 0,
  notes text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (report_date, staff_id)
);
alter table daily_reports enable row level security;
drop policy if exists anon_all on daily_reports;
create policy anon_all on daily_reports for all to anon using (true) with check (true);

-- ---------------------------------------------------------
-- STAFF SALARY / PAYROLL
-- ---------------------------------------------------------
create table if not exists staff_salary (
  id uuid primary key default gen_random_uuid(),
  staff_id uuid not null unique references staff(id) on delete cascade,
  basic_salary numeric(12,2) not null default 0,
  transport_allowance numeric(12,2) not null default 0,
  other_allowance numeric(12,2) not null default 0,
  standard_deduction numeric(12,2) not null default 0,
  payment_method text not null default 'cash',
  payment_account text,
  notes text,
  updated_at timestamptz not null default now()
);

create table if not exists salary_advances (
  id uuid primary key default gen_random_uuid(),
  staff_id uuid not null references staff(id),
  amount numeric(12,2) not null,
  repaid numeric(12,2) not null default 0,
  monthly_deduction numeric(12,2) not null default 0,   -- 0 = take the whole balance at once
  advance_date date not null default current_date,
  reason text,
  given_by uuid references staff(id),
  created_at timestamptz not null default now()
);

create table if not exists payslips (
  id uuid primary key default gen_random_uuid(),
  payslip_no text not null unique,
  staff_id uuid not null references staff(id),
  period_month date not null,                           -- first day of the month
  basic_salary numeric(12,2) not null default 0,
  gross_pay numeric(12,2) not null default 0,
  total_deductions numeric(12,2) not null default 0,
  net_pay numeric(12,2) not null default 0,
  status text not null default 'draft',                 -- draft | paid
  payment_method text,
  paid_at timestamptz,
  paid_by uuid references staff(id),
  notes text,
  created_by uuid references staff(id),
  created_at timestamptz not null default now(),
  unique (staff_id, period_month)
);

create table if not exists payslip_items (
  id uuid primary key default gen_random_uuid(),
  payslip_id uuid not null references payslips(id) on delete cascade,
  kind text not null,                                   -- earning | deduction
  label text not null,
  amount numeric(12,2) not null,
  advance_id uuid references salary_advances(id)
);

alter table staff_salary enable row level security;
alter table salary_advances enable row level security;
alter table payslips enable row level security;
alter table payslip_items enable row level security;
drop policy if exists anon_all on staff_salary;     create policy anon_all on staff_salary     for all to anon using (true) with check (true);
drop policy if exists anon_all on salary_advances;  create policy anon_all on salary_advances  for all to anon using (true) with check (true);
drop policy if exists anon_all on payslips;         create policy anon_all on payslips         for all to anon using (true) with check (true);
drop policy if exists anon_all on payslip_items;    create policy anon_all on payslip_items    for all to anon using (true) with check (true);


