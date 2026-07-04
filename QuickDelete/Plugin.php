<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 文章快捷删除与自定义字段样式，适配Typecho 1.3.0 + PHP8.4
 *
 * @package QuickDelete
 * @author 松子分享
 * @version 2.0.0
 * @link https://cloud.szfx.top/typecho/145.html
 */
class QuickDelete_Plugin implements Typecho_Plugin_Interface
{
    private static $rendered = false;
    public static function activate()
    {
        Typecho_Plugin::factory('admin/footer.php')->end = array('QuickDelete_Plugin', 'render');
        return _t('插件已激活，请进入设置面板配置选项');
    }
    public static function deactivate()
    {
        return _t('插件已禁用');
    }
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $fieldWidth = new Typecho_Widget_Helper_Form_Element_Text(
            'fieldWidth',
            NULL,
            '100%',
            _t('自定义字段输入框宽度'),
            _t('设置文章编辑页自定义字段输入框的 CSS 宽度值，例如：100%、500px、30em 等。<br>留空则不修改默认宽度。此设置同时影响字段名和字段值输入框。')
        );
        $form->addInput($fieldWidth);
    }
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}
    public static function render()
    {
        if (self::$rendered) return;
        self::$rendered = true;
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        try {
            $options = Typecho_Widget::widget('Widget_Options');
        } catch (Exception $e) {
            return;
        }
        $pluginOptions = $options->plugin('QuickDelete');
        if (strpos($requestUri, 'manage-posts') !== false) {
            self::renderManagePosts();
        }
        if (strpos($requestUri, 'write-post') !== false) {
            self::renderCustom($pluginOptions);
        }    
        if (strpos($requestUri, 'write-post') !== false && isset($_GET['cid']) && intval($_GET['cid']) > 0) {    
            self::renderWritePost();
        }
    }
    
    /**
     * 自定义字段宽度
     */
    private static function renderCustom($pluginOptions)
    {
        $fieldWidth = isset($pluginOptions->fieldWidth) ? trim($pluginOptions->fieldWidth) : '';
        ?>
        <style>
        <?php if (!empty($fieldWidth)): ?>
        #custom-field table { table-layout: auto !important; }
        #custom-field colgroup col { width: auto !important; }
        #custom-field input[type="text"],
        #custom-field textarea,
        #custom-field select {
            width: <?php echo htmlspecialchars($fieldWidth); ?> !important;
            max-width: none !important;
            box-sizing: border-box !important;
        }
        <?php endif; ?>
        </style>        
        <?php
    }
    
    /**
     * 文章管理页面：新增删除按钮（打开编辑页新窗口并携带 auto_delete=1 参数）
     */
    private static function renderManagePosts()
    {
        ?>
        <style>
        .qd-del-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            cursor: pointer;
            color: #999;
            text-decoration: none !important;
            transition: color 0.15s ease;
        }
        .qd-del-icon:hover {
            color: #c0392b;
        }
        .qd-del-icon svg {
            display: block;
            width: 16px;
            height: 16px;
        }
        </style>
        <script>
        (function() {
            // 构建 DOM 表头
            var theadTr = document.querySelector('.typecho-list-table thead tr');
            if (theadTr) {
                var lastTh = theadTr.querySelector('th:last-child');
                if (lastTh) lastTh.classList.remove('typecho-radius-tright');
                var newTh = document.createElement('th');
                newTh.className = 'typecho-radius-tright';
                newTh.style.width = '30px';
                theadTr.appendChild(newTh);
            }

            // 构建 DOM 表行
            var rows = document.querySelectorAll('.typecho-list-table tbody tr');
            for (var i = 0; i < rows.length; i++) {
                (function(tr) {
                    var cb = tr.querySelector('input[type="checkbox"][name="cid[]"]');
                    if (!cb || !cb.value) return;
                    var cid = cb.value;
                    
                    var lastTd = tr.querySelector('td:last-child');
                    if (lastTd) lastTd.classList.remove('typecho-radius-tright');
                    
                    var newTd = document.createElement('td');
                    var del = document.createElement('a');
                    del.href = 'javascript:;';
                    del.className = 'qd-del-icon';
                    del.title = '删除';
                    del.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>';
                    
                    del.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (!confirm('确认删除这篇文章？\n删除后将不可恢复。')) return;
                        
                        // 打开编辑页并携带 auto_delete=1 参数，表示需要自动删除
                        var editUrl = '/admin/write-post.php?cid=' + cid + '&auto_delete=1';
                        var deleteWin = window.open(editUrl, '_blank');
                        
                        // 定时检测编辑页是否加载完成，若完成则触发其中的自动删除按钮
                        var checkLoaded = setInterval(function() {
                            try {
                                if (deleteWin.document.readyState === 'complete') {
                                    // 编辑页中的自动删除触发器会自己执行，这里无需额外操作
                                    clearInterval(checkLoaded);
                                }
                            } catch (e) {
                                clearInterval(checkLoaded);
                            }
                        }, 200);
                        
                        // 如果弹窗被浏览器拦截，则当前页面跳转到编辑页（备选方案）
                        setTimeout(function() {
                            if (deleteWin.closed) {
                                location.href = editUrl;
                            }
                        }, 1000);
                    });
                    
                    newTd.appendChild(del);
                    tr.appendChild(newTd);
                })(rows[i]);
            }
        })();
        </script>
        <?php
    }

    /**
     * 文章编辑页面：删除按钮（仅当 auto_delete=1 时自动删除，否则需手动确认）
     */
    private static function renderWritePost()
    {
        // 检测是否为自动删除模式（仅由 URL 参数 auto_delete=1 决定）
        $isAutoDelete = isset($_GET['auto_delete']) && $_GET['auto_delete'] == '1';
        ?>
        <style>
        .qd-delete-btn {
            display: inline-block;
            padding: 0 10px;
            height: 28px;
            line-height: 28px;
            font-size: 12px;
            color: #b94a48;
            border: 1px solid #e0bfbf;
            border-radius: 2px;
            background: #fdf0f0;
            cursor: pointer;
            margin-right: 8px;
            transition: all 0.15s ease;
            vertical-align: middle;
            white-space: nowrap;
            font-family: inherit;
        }
        .qd-delete-btn:hover {
            background: #c0392b;
            color: #fff;
            border-color: #c0392b;
        }
        .qd-delete-btn:active {
            background: #a3302c;
        }
        </style>
        <script>
        (function() {
            var nativeForm = document.getElementById('write-post') || document.querySelector('form');
            if (!nativeForm) return;
            var formAction = nativeForm.action;
            var tokenInput = nativeForm.querySelector('input[name="_token"]');
            var formToken = tokenInput ? tokenInput.value : '';
            var previewBtn = document.getElementById('btn-preview');
            if (!previewBtn) return;

            // 仅由 URL 参数 auto_delete 决定是否为自动删除模式
            var isAutoDelete = <?php echo $isAutoDelete ? 'true' : 'false'; ?>;
            
            var deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'qd-delete-btn';
            deleteBtn.innerHTML = '删除文章';
            previewBtn.parentNode.insertBefore(deleteBtn, previewBtn);
            
            // 删除执行函数
            function doDelete() {
                var params = new URLSearchParams(window.location.search);
                var cid = params.get('cid');
                if (!cid) { alert('无法获取文章 ID'); return false; }

                var form = document.createElement('form');
                form.method = 'post';
                form.action = formAction;
                form.style.display = 'none';

                var doInput = document.createElement('input');
                doInput.type = 'hidden'; doInput.name = 'do'; doInput.value = 'delete';
                form.appendChild(doInput);
                if (formToken) {
                    var tInput = document.createElement('input');
                    tInput.type = 'hidden'; tInput.name = '_token'; tInput.value = formToken;
                    form.appendChild(tInput);
                }
                var cidInput = document.createElement('input');
                cidInput.type = 'hidden'; cidInput.name = 'cid[]'; cidInput.value = cid;
                form.appendChild(cidInput);
                document.body.appendChild(form);

                form.submit();
                // 提交后跳转回文章列表页
                setTimeout(function(){
                    location.href = "/admin/manage-posts.php";
                }, 80);
                return true;
            }

            // 绑定点击事件
            deleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // 自动删除模式下已经跳过 confirm，但为防止手动点击时误判，此处仍检查 isAutoDelete
                if (!isAutoDelete && !confirm('确认删除这篇文章？\n删除后将不可恢复。')) return;
                doDelete();
            });

            // 如果是自动删除模式，页面加载后自动触发删除（无需 confirm）
            if (isAutoDelete) {
                // 稍等 DOM 元素完全就绪再执行
                setTimeout(function() {
                    if (deleteBtn) {
                        // 直接调用删除逻辑，不经过 confirm
                        doDelete();
                    }
                }, 300);
            }
        })();
        </script>
        <?php
    }
}
