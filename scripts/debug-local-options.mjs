import mysql from 'mysql2/promise';

async function main() {
  const conn = await mysql.createConnection({
    host: 'localhost',
    user: 'root',
    password: 'root',
    database: 'local',
  });

  const [rows] = await conn.execute(
    "SELECT option_name, option_value FROM wp_options WHERE option_name IN ('plt_settings','plt_next_match_settings','plt_active_season_id','plt_last_season_check_at')"
  );

  console.log(JSON.stringify(rows, null, 2));
  await conn.end();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
