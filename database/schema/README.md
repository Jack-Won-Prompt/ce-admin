# 기준 스키마 (mysql-schema.sql)

빈 DB 에 이 시스템을 세울 때 **먼저 들어가는 표 구조**다.

> **2026-09-20 부터는 마이그레이션만으로도 선다.** 뼈대 표를 만드는 장을 넣었다
> (`2026_04_01_000000_create_base_tables`). 이 파일은 빠른 길로 남겨 둔다 —
> 158 장을 도는 것보다 덤프 한 장을 넣는 편이 훨씬 빠르다.

## 왜 두는가

마이그레이션이 158 장 있는데, 뼈대가 되는 네 표에는 만드는 장이 없다 —
`users` · `patients` · `prescriptions` · `orders` 는 고치는 장(add)만 있고
`Schema::create` 가 한 장도 없다. 라라벨 기본 표(`sessions` · `cache` · `jobs` ·
`password_reset_tokens`)도 마찬가지다.

이 시스템의 DB 는 SQL 덤프로 세워졌고 마이그레이션은 그 위에 얹혀 왔다. 그래서
돌고 있는 서버는 멀쩡하지만(150 장 모두 Ran · 대기 0), **빈 DB 에 `migrate` 를
돌리면 두 번째 장에서 멈춘다.**

```
2026_04_15_000002_add_created_by_to_prescriptions_table
  → SQLSTATE[42S02] Base table or view not found: 'prescriptions'
```

새 서버 구축 · 개발자 합류 · 시험 DB 재생성 · CI 어느 쪽도 저장소만으로는 설 수
없었다. 그 근거가 추적되지 않는 파일 하나(`colo_unicorn.sql`)뿐이었다.

## 어떻게 쓰는가

라라벨이 알아서 쓴다. `php artisan migrate` 가 **마이그레이션 기록이 비어 있을
때만** 이 파일을 먼저 넣고, 그 뒤 여기 담기지 않은 장만 이어 돌린다.

```bash
# 빈 DB 에 세우기
php artisan migrate        # mysql-schema.sql 을 넣고 나머지를 이어 돌린다
```

이미 돌고 있는 서버에는 **아무 일도 하지 않는다** — 마이그레이션 기록이 차 있어
이 파일을 건너뛴다. 운영에 영향이 없다.

## 무엇이 담겨 있나

* 표 구조 77 개 (`CREATE TABLE` 만)
* `migrations` 표의 기록 169 줄 — 어디까지 돌았는지를 새 DB 에 그대로 옮긴다

**업무 자료는 한 줄도 없다.** `INSERT` 는 전부 `migrations` 표의 것이고, 주민번호 ·
연락처 · 이름은 들어 있지 않다.

## 다시 뽑을 때

칸을 크게 바꾼 뒤에는 이 파일도 새로 뽑는다.

```bash
php artisan schema:dump      # --prune 은 쓰지 않는다 (마이그레이션 장을 지운다)
```

`--prune` 을 붙이면 여기 담긴 장들을 지운다. 그 장들에는 「왜 그렇게 고쳤는가」가
적혀 있어 지우면 이유가 사라진다. 붙이지 않는다.

### 뽑은 뒤에 반드시 지울 줄

서버가 MariaDB 라, 뽑은 파일에 MariaDB 전용 표시가 **두 군데** 들어간다.

```
/*M!999999\- enable the sandbox mode */
```

첫 줄과, 마이그레이션 기록을 덧붙이는 자리(INSERT 바로 앞)다. 이 줄이 있으면
다른 클라이언트가 읽을 때 그 자리에서 멈춘다.

```
ERROR at line 1: Unknown command '\-'.
```

뽑은 뒤 두 줄을 지운다.

```bash
sed -i '/enable the sandbox mode/d' database/schema/mysql-schema.sql
```

`mysqldump: unknown variable 'column-statistics=0'` 경고는 그냥 두어도 된다 —
라라벨이 MySQL 전용 옵션을 먼저 넣어 보고, 안 되면 빼고 다시 뽑는다.

### 뽑은 뒤 확인

빈 DB 에 실제로 서는지 본다. 운영ㆍ로컬 DB 를 건드리지 않게 시험용을 따로 만든다.

```bash
mysql -u root -e "CREATE DATABASE ceadmin_schema_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
DB_DATABASE=ceadmin_schema_test php artisan migrate --force
#   INFO  Loading stored database schemas.
#   INFO  Nothing to migrate.
mysql -u root -e "DROP DATABASE ceadmin_schema_test"
```

`mysql` 이 PATH 에 없으면 라라벨이 「'mysql' is not recognized」로 멈춘다.
XAMPP 는 `/e/xampp/mysql/bin` 에 있다.
