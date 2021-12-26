</div>
<footer class="small-font">
    <div class="footer_wrapper">
        {$dev="<a rel='author' target='blank'
            href='https://telegram.me/CyberStainBot'>
            CyberStainBot
        </a>"}
        {t params=['@dev'=>$dev]}Code by @dev{/t}

        &middot;

        {$designer="<a rel='author' target='blank'
            href='https://telegram.me/CyberStainBot'>
            CyberStainBot
        </a>"}
        {t params=['@designer' => $designer]}Design by @designer{/t}

        &middot;

        <a rel="noopener" target="_blank" href="https://telegram.me">
            {t}Sponsor{/t}
        </a>

        &middot;

        {$youtubedl="<a href='http://youtube.com'>
            youtube-dl
        </a>"}
        {t params=['@youtubedl'=>$youtubedl]}Based on @youtube{/t}
    </div>
</footer>
</div>
{if isset($debug_render)}
    {$debug_render->render()}
{/if}
</body>
</html>
