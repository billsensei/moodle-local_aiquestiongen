<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component local_aiquestiongen, language 'ja'.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_approve'] = '承認';
$string['action_approveall'] = '保留中をすべて承認';
$string['action_cancel'] = 'キャンセル';
$string['action_edit'] = '編集';
$string['action_regenerate'] = '再生成';
$string['action_regeneratego'] = 'この問題を再生成';
$string['action_reject'] = '却下';
$string['action_rejectall'] = '保留中をすべて却下';
$string['action_save'] = '変更を保存';
$string['action_undo'] = '元に戻す';
$string['aiheading'] = 'AI接続';
$string['aiheading_desc'] = 'プラグインがAIモデルに接続する方法です。コアAIは「サイト管理 > AI」で設定したプロバイダーを使います。直接APIはプロバイダーを直接呼び出すため特定のモデルを使えますが、キーはこのプラグインが保存します。';
$string['aimode'] = '接続モード';
$string['aimode_core'] = 'MoodleのAIサブシステム（推奨）';
$string['aimode_desc'] = 'コアAIを推奨します。ログの一元管理、ポリシーへの同意、利用制限が使えます。';
$string['aimode_direct'] = '直接API（ClaudeまたはOpenAI互換）';
$string['aiquestiongen:publish'] = 'AI生成問題を問題バンクに公開する';
$string['aiquestiongen:use'] = 'AI問題ジェネレーターを使用する';
$string['alsoaccepted'] = 'その他の正解:';
$string['answerlabel'] = '選択肢 {$a}';
$string['articletext'] = '記事本文';
$string['articletext_help'] = 'ここに記事を貼り付けるか、下でファイルをアップロードしてください。どちらか一方のみ指定できます。';
$string['articletitle'] = '記事タイトル';
$string['blankanswer'] = '正しい語:';
$string['cefrlevel'] = 'CEFRレベル';
$string['clozecount'] = '穴埋め問題の数（学生が語を入力）';
$string['clozecount_help'] = 'それぞれ空所が1つの1文です。学生は空所に入る語を入力します。他の正解も受け付けられます。';
$string['confirmrejectall'] = 'このグループの保留中の{$a}問をすべて却下しますか？あとで個別に元に戻せます。';
$string['copyrightnotice'] = '利用する権利のある文章のみをアップロードまたは貼り付けてください。本文は問題の下書きを作成するため外部のAIプロバイダに送信されます。承認するまで問題バンクには何も追加されません。';
$string['correctanswer'] = '正解';
$string['correctlegend'] = '選択肢。正解を1つ選んでください。';
$string['countsintro'] = '種類ごとに下書きを作る問題数を選んでください。合計は{$a}問以下にしてください。';
$string['dailyjoblimit'] = 'ユーザーごとの1日あたりのジョブ数';
$string['dailyjoblimit_desc'] = '各ユーザーが1日に開始できる生成ジョブの最大数です。0で無制限になります。';
$string['dailyregenlimit'] = '1日あたりの再生成回数（ユーザーごと）';
$string['dailyregenlimit_desc'] = '各ユーザーが1日に下書き問題を再生成できる最大回数です（すべての下書きの合計）。AIが使える代替問題を作れなかった場合も、リクエストごとに数えます。1つの下書きの再生成は最大5回までです。0にすると1日の上限はありません。';
$string['directapikey'] = 'APIキー';
$string['directapikey_desc'] = '暗号化してMoodleのデータベースに保存されます。暗号化キーはサイトのデータディレクトリ内のsecretフォルダにあるファイルなので、データベースと一緒にバックアップしてください。保存したキーは再表示されません。変更するには新しいキーを入力してください。';
$string['directendpoint'] = 'エンドポイントのベースURL';
$string['directendpoint_desc'] = 'Claudeでは空欄なら https://api.anthropic.com を使います。OpenAI互換サービスでは /v1 までのベースURL（例: https://api.openai.com/v1）を入力します。アドレスは https:// で始まる必要があります（http:// は、同じサーバー上で動くモデル用の localhost のみ可）。リクエストはMoodleのHTTPセキュリティ設定を通り、ローカルやプライベートなアドレスは既定でブロックされます。';
$string['directmaxtokens'] = '返信の最大トークン数';
$string['directmaxtokens_desc'] = '要求する返信の最大長です。長いジョブで途中で切れる場合は増やしてください。';
$string['directmodel'] = 'モデル';
$string['directmodel_desc'] = '各リクエストで送信するモデル名です。';
$string['directprovider'] = '直接APIの種類';
$string['directprovider_desc'] = '使用するリクエスト形式です。';
$string['directtimeout'] = 'リクエストのタイムアウト（秒）';
$string['directtimeout_desc'] = '1回の返信を待つ時間です。';
$string['disabled'] = 'このサイトではAI問題ジェネレーターは無効になっています。';
$string['draftsheading'] = '下書き問題 {$a}件';
$string['enabled'] = 'AI問題ジェネレーターを有効にする';
$string['enabled_desc'] = '無効にすると、コースメニューのリンクが非表示になり、新しい記事を追加できなくなり、これ以上AIへのリクエストは行われません（待機中のジョブや問題の再生成も含みます）。すでにある下書きの確認と公開は引き続き行えます。';
$string['err_aiempty'] = 'AIプロバイダーから空の応答が返されました。';
$string['err_aifailed'] = 'AIプロバイダーがテキストを生成できませんでした: {$a}';
$string['err_badquiz'] = 'その小テストは使用できません。一覧から選択してください。';
$string['err_badstatus'] = 'その状態には設定できません。';
$string['err_badtarget'] = 'その問題バンクまたはカテゴリは使用できません。一覧から選択してください。';
$string['err_cannotapprove'] = 'この問題はまだ承認できません: {$a}。先に編集してください。';
$string['err_cefr'] = '有効なCEFRレベルを選択してください。';
$string['err_count'] = '問題数の合計は1から{$a}の間にしてください。';
$string['err_dailylimit'] = '1日の生成ジョブの上限（{$a}件）に達しました。明日もう一度お試しください。';
$string['err_directauth'] = 'AIサービスがリクエストを拒否しました（HTTP {$a}）。APIキーと、そのキーでこのモデルを使えるかを確認してください。';
$string['err_directbadresponse'] = 'AIサービスの返信を読み取れませんでした。';
$string['err_directconnect'] = 'AIサービスに接続できませんでした。エンドポイント、ネットワーク、サイトのHTTPセキュリティ設定を確認してください。';
$string['err_directhttp'] = 'AIサービスがエラーを返しました（HTTP {$a->status}）。{$a->detail}';
$string['err_directkey'] = '保存されたAPIキーを読み取れませんでした。サイトを移行または復元した場合に起こることがあります。管理者がAPIキーをもう一度入力する必要があります。';
$string['err_directnotconfigured'] = '直接APIモードが選択されていますが、設定が完了していません。管理者がAPIキーとモデル（OpenAI互換ではエンドポイントも）を入力する必要があります。';
$string['err_directrate'] = 'AIサービスから、リクエストが多すぎるか利用枠を使い切ったと返されました。しばらくしてからお試しください。';
$string['err_docxinvalid'] = '.docxファイルを読み込めませんでした。ファイルが破損しているか、Word文書ではない可能性があります。';
$string['err_emptytext'] = '記事からテキストを取得できませんでした。';
$string['err_fieldlength'] = '入力欄が長すぎます。各欄は{$a}文字までです。';
$string['err_nobanks'] = 'このコースには、問題を追加できる問題バンクがありません。先にコースで問題バンクを作成してから戻ってください。';
$string['err_noitems'] = 'AIから使用可能な問題が返されませんでした。';
$string['err_notextorfile'] = 'テキストを貼り付けるか、ファイルをアップロードしてください。';
$string['err_nothingapproved'] = '公開できる承認済みの問題がありません。先に下書きを承認してください。';
$string['err_notowner'] = '自分で追加した記事からのみ問題を生成できます。';
$string['err_pdffailed'] = 'PDFをテキストに変換できませんでした。';
$string['err_pdfnotconfigured'] = '管理者がpdftotextのパスを設定していないため、PDFからのテキスト抽出は利用できません。';
$string['err_published'] = 'この問題はすでに公開されており、ここでは変更できません。';
$string['err_quizcreate'] = 'このコースには小テストを追加できません。';
$string['err_regendaily'] = '1日の再生成の上限（{$a}回）に達しました。問題を手動で編集するか、明日もう一度お試しください。';
$string['err_regenfailed'] = 'AIが有効な差し替え問題を作れなかったため、元の問題を残しました。もう一度試すか、手で編集してください。';
$string['err_regenlimit'] = 'この問題はすでに{$a}回再生成されています。代わりに手で編集してください。';
$string['err_textlong'] = '記事が長すぎます。最大は{$a}文字です。';
$string['err_textorfile'] = '貼り付けたテキストとファイルのどちらか一方のみを指定してください。';
$string['err_unexpected'] = '予期しないエラーにより生成が停止しました。管理者にサイトのログの確認を依頼してください。';
$string['err_unparseable'] = '2回試しても、AIの応答を問題として読み取れませんでした。';
$string['err_unreadable'] = 'アップロードされたファイルを読み込めませんでした。';
$string['err_unsupportedtype'] = '「{$a}」形式のファイルはサポートされていません。.txt、.docx、.pdf、.htmlのいずれかを使用してください。';
$string['essaycount'] = '記述式（自由記述）問題の数';
$string['essaycount_help'] = '下書きを作成する記述式の問題数です。それぞれに模範解答と採点ガイドが付きます。';
$string['event_itemapproved'] = '下書き問題が承認されました';
$string['event_questionspublished'] = 'AI生成問題が公開されました';
$string['feedbackiffalse'] = '学生が「偽」と答えた場合のフィードバック';
$string['feedbackiftrue'] = '学生が「真」と答えた場合のフィードバック';
$string['feedbacklabel'] = '選択肢 {$a} のフィードバック';
$string['feedbacklanguage'] = '解答フィードバックの言語';
$string['gapcount'] = '穴埋め問題の数（学生が一覧から選択）';
$string['gapcount_help'] = 'それぞれ空所が1つの1文です。学生は4つの選択肢のドロップダウンから語を選びます。初級レベル向きです。';
$string['generatedrafts'] = '下書き問題を生成';
$string['generateheading'] = '問題を生成';
$string['generatequestions'] = 'AI問題ジェネレーター';
$string['generatingfrom'] = '記事: {$a}';
$string['group_essay'] = '記述式（自由記述）';
$string['group_gapselect'] = '穴埋め（一覧から選択）';
$string['group_multianswer'] = '穴埋め（入力式）';
$string['group_multichoice'] = '多肢選択';
$string['group_truefalse'] = '真偽';
$string['includepassage'] = '読解文を含める';
$string['includepassage_help'] = '記事を説明（description）項目として問題バンクに追加し、reading-comprehension のタグを付けます。小テストで読解問題の上に表示できます。1つのジョブにつき1回だけ追加されます。';
$string['issue_answercount'] = '選択肢が4つではありません';
$string['issue_badalternatives'] = 'その他の正解の一覧が正しくありません';
$string['issue_badanswerchars'] = '解答に ~ または * か改行が含まれています';
$string['issue_badfocus'] = '焦点が語彙・文法・読解のいずれでもありません';
$string['issue_badstemchars'] = '文に { } または [[ ]] が含まれており、問題の形式と衝突します';
$string['issue_badtype'] = '問題タイプがmultichoiceではありません';
$string['issue_blankanswer'] = '空の選択肢があります';
$string['issue_clozesyntax'] = '入力式の解答を有効な穴埋め問題に変換できませんでした';
$string['issue_correctcount'] = '正解がちょうど1つではありません';
$string['issue_distractorcount'] = '誤答の選択肢がちょうど3つではありません';
$string['issue_duplicate'] = '同じ選択肢が2つあります';
$string['issue_noanswer'] = '正しい語がありません';
$string['issue_noblank'] = '文に空所（____）がちょうど1つありません';
$string['issue_nocorrect'] = '正解（真または偽）がありません';
$string['issue_nomodelanswer'] = '模範解答がありません';
$string['issue_norubric'] = '採点ガイドがありません';
$string['issue_nostem'] = '問題文がありません';
$string['issue_targetmissing'] = '対象の単語が記事内にありません';
$string['jobheading'] = '問題の生成';
$string['jobstatus'] = '状態: {$a}';
$string['jobwaiting'] = 'バックグラウンドで生成中です。このページは自動的に更新されます。';
$string['lang_english'] = '英語';
$string['lang_japanese'] = '日本語';
$string['live_approved'] = '問題 {$a} を承認しました。';
$string['live_bulk'] = '{$a->changed}件を処理しました。要確認のため{$a->skipped}件をスキップしました。';
$string['live_pending'] = '問題 {$a} を保留に戻しました。';
$string['live_regenerated'] = '問題 {$a} を差し替えました。保留中です。';
$string['live_regenerating'] = '問題 {$a} を再生成しています。';
$string['live_rejected'] = '問題 {$a} を却下しました。';
$string['live_saved'] = '問題 {$a} を保存しました。';
$string['markcorrect'] = '選択肢 {$a} が正解';
$string['markingguide'] = '採点ガイド';
$string['maxarticlelength'] = '記事の最大文字数';
$string['maxarticlelength_desc'] = '1回のアップロードで受け付ける記事本文の最大文字数です。';
$string['maxquestionsperjob'] = '1ジョブあたりの最大問題数';
$string['maxquestionsperjob_desc'] = '1回の生成ジョブで依頼できる問題数の上限です。';
$string['mccount'] = '多肢選択問題の数';
$string['mccount_help'] = '下書きを作成する語彙・文法の多肢選択問題の数です。上限は管理者が設定します。';
$string['modelanswer'] = '模範解答';
$string['needschecking'] = '要確認';
$string['onewordperline'] = '1行に1語（または句）。';
$string['optionnewcategory'] = '{$a->bank}: 新しいカテゴリ「{$a->name}」';
$string['partialfailure'] = '一部の問題を生成できませんでした: {$a}';
$string['pdftotextpath'] = 'pdftotextのパス';
$string['pdftotextpath_desc'] = 'pdftotext実行ファイル（poppler-utils）のフルパスです（例：/usr/bin/pdftotext）。空欄の場合、PDFのアップロードは無効になります。';
$string['pluginname'] = 'AI問題ジェネレーター';
$string['privacy:metadata:aiprovider'] = '問題の下書きを生成するため、記事本文は設定された外部のAIプロバイダに送信されます。';
$string['privacy:metadata:aiprovider:articletext'] = '記事の本文、またはその一部。';
$string['privacy:metadata:aiprovider:prompt'] = 'CEFRレベルや問題タイプなどの生成指示。';
$string['privacy:metadata:core_files'] = 'アップロードされた元記事ファイルはMoodleのファイルシステムに保存されます。';
$string['privacy:metadata:local_aiquestiongen_item'] = '元記事から生成された問題の下書き。';
$string['privacy:metadata:local_aiquestiongen_item:jobid'] = '下書きが属する生成ジョブ。';
$string['privacy:metadata:local_aiquestiongen_item:payload'] = 'JSON形式の下書き問題の内容。';
$string['privacy:metadata:local_aiquestiongen_item:qtype'] = '下書きの問題タイプ。';
$string['privacy:metadata:local_aiquestiongen_item:questionid'] = '下書きの公開後の問題バンクID。';
$string['privacy:metadata:local_aiquestiongen_item:status'] = '下書きのレビュー状態。';
$string['privacy:metadata:local_aiquestiongen_item:timemodified'] = '下書きが最後に変更された日時。';
$string['privacy:metadata:local_aiquestiongen_job'] = '元記事に対して開始された生成の実行。';
$string['privacy:metadata:local_aiquestiongen_job:error'] = '実行時のエラーメッセージ。';
$string['privacy:metadata:local_aiquestiongen_job:provider'] = '実行に使用されたAIプロバイダ。';
$string['privacy:metadata:local_aiquestiongen_job:settings'] = 'JSON形式の生成設定。';
$string['privacy:metadata:local_aiquestiongen_job:sourceid'] = '実行が属する元記事。';
$string['privacy:metadata:local_aiquestiongen_job:status'] = '実行の状態。';
$string['privacy:metadata:local_aiquestiongen_job:timecreated'] = '実行が作成された日時。';
$string['privacy:metadata:local_aiquestiongen_job:tokensused'] = '実行で使用されたAIトークン数。';
$string['privacy:metadata:local_aiquestiongen_source'] = 'ユーザーがアップロードまたは貼り付けた元記事。';
$string['privacy:metadata:local_aiquestiongen_source:courseid'] = '記事が追加されたコース。';
$string['privacy:metadata:local_aiquestiongen_source:sourcefileitemid'] = 'アップロードされた元ファイルのアイテムID。';
$string['privacy:metadata:local_aiquestiongen_source:text'] = '記事から抽出されたテキスト。';
$string['privacy:metadata:local_aiquestiongen_source:timecreated'] = '記事が追加された日時。';
$string['privacy:metadata:local_aiquestiongen_source:title'] = '記事のタイトル。';
$string['privacy:metadata:local_aiquestiongen_source:userid'] = '記事を追加したユーザーのID。';
$string['provider_claude'] = 'Anthropic Claude（Messages API）';
$string['provider_openai'] = 'OpenAI互換（chat completions）';
$string['publishbutton'] = '承認済みの{$a}問を公開';
$string['publishdone'] = '{$a}問を問題バンクに公開しました。';
$string['publishfailed'] = '{$a}問は公開できませんでした。承認済みのままなので、もう一度お試しください。';
$string['publishheading'] = '問題バンクへ公開';
$string['publishintro'] = '承認済みの{$a}問を問題バンクに追加します。問題には ai-generated、CEFRレベル、記事タイトルのタグが付きます。';
$string['publishlink'] = '承認済みの問題を公開';
$string['publishnote'] = '承認した問題は、問題バンクに公開するまでここで待機します。';
$string['publishpassagedone'] = '記事も読解文として追加しました。';
$string['publishquizdone'] = '{$a->count}問を小テスト「{$a->name}」に追加しました。';
$string['publishquizfailed'] = '問題は公開されましたが、小テストに追加できませんでした: {$a}';
$string['publishtarget'] = '問題バンクとカテゴリ';
$string['publishtarget_help'] = '既存のカテゴリを選ぶか、記事名の新しいカテゴリをバンク内に作成します。';
$string['questionnumber'] = '問題 {$a} を編集';
$string['quizchoice'] = '小テスト';
$string['quizchoice_help'] = '公開した問題を小テストに入れることもできます。新しい小テストは非表示で、全問が1ページに作成されるので、学生に見せる前に確認できます。読解文を含める場合は最初に置かれます。すでに受験がある小テストは選べません。';
$string['quizexisting'] = '既存の小テスト「{$a}」に追加';
$string['quiznew'] = '「{$a}」という新しい小テストを作成';
$string['quiznone'] = '小テストに追加しない';
$string['readingpassage'] = '読解文';
$string['regencomment'] = '何を変えますか？（任意）';
$string['regenerateask'] = '教師はこの問題を別の問題に差し替えることを望んでいます。';
$string['regenerateaskcomment'] = '教師はこの問題を別の問題に差し替えることを望んでいます。教師のコメント: {$a}';
$string['regenhelp'] = '例: もっと易しく、別の単語で、記事の別の部分から出題など。古い版は置き換えられ、保留に戻ります。';
$string['savesource'] = '記事を保存';
$string['sourcefile'] = '記事ファイル';
$string['sourcefile_help'] = '.txt、.docx、.pdf、.htmlファイルをアップロードします。PDFを使用するには、管理者がpdftotextを設定する必要があります。';
$string['sourcepassage'] = '元の記事';
$string['sourcesaved'] = '記事を保存しました。';
$string['status_approved'] = '承認済み';
$string['status_done'] = '完了';
$string['status_failed'] = '失敗';
$string['status_pending'] = '保留中';
$string['status_published'] = '公開済み';
$string['status_queued'] = '開始待ち';
$string['status_rejected'] = '却下済み';
$string['status_running'] = '実行中';
$string['stemlabel'] = '問題文';
$string['targetpoints'] = '対象の語彙・文法項目';
$string['targetpoints_help'] = '任意。重点を置く単語や文法項目をカンマで区切って入力します。語彙は記事内に出現するものにしてください。';
$string['task_generatequestions'] = '記事からAI問題を生成';
$string['tfcorrectis'] = '正解:';
$string['tfcorrectlegend'] = 'この文は';
$string['tfcount'] = '真偽問題の数';
$string['tfcount_help'] = '下書きを作成する、読解の真偽問題（文）の数です。';
$string['tryagain'] = 'もう一度試す';
$string['uploadheading'] = '記事のアップロード';
$string['wrongchoices'] = '誤答の選択肢:';
